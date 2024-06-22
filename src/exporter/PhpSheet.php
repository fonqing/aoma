<?php

namespace aoma\exporter;

use aoma\Exporter;
use Cache\Adapter\Redis\RedisCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use aoma\fast\BaseController;
use support\Log;
use support\Response;

class PhpSheet extends Exporter
{
    private array $columns = [];
    private string $fileName = '';
    private string $title = '';

    /**
     * @var BaseController
     */
    private BaseController $context;

    private $data;

    private array $border = [
        'borderStyle' => Border::BORDER_THIN,
        'color' => ['argb' => '00000000'],
    ];

    public function __construct(array $columns = [])
    {
        if (!empty($columns)) {
            $this->columns = $columns;
        }
    }

    public function setColumns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function setFileName($name): static
    {
        $this->fileName = $name;
        return $this;
    }

    public function setTitle($title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setControllerContext($controller): static
    {
        $this->context = $controller;
        return $this;
    }

    public function setDataQuery($data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @throws \Exception
     */
    private function check(): void
    {
        if (empty($this->columns)) {
            throw new \Exception('字段配置为空,无法导出');
        }
        if (empty($this->data)) {
            throw new \Exception('数据查询为空,无法导出');
        }
        if (empty($this->title)) {
            throw new \Exception('未设置导出表格标题');
        }
        if (empty($this->fileName)) {
            throw new \Exception('未设置导出文件名');
        }
    }

    private function enableCache(): void
    {
        $client = new \Redis();
        $client->connect('127.0.0.1', 6379);
        $client->select(4);
        $pool = new RedisCachePool($client);
        $simpleCache = new SimpleCacheBridge($pool);
        Settings::setCache($simpleCache);
    }

    private function drawTitle($sheet, $row, $title): void
    {
        $sheet->mergeCells([1, $row, count($this->columns), $row]);
        $sheet->getStyle([1, $row, count($this->columns), $row])
            ->applyFromArray([
                'font' => ['bold' => true, 'size' => 18],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        $sheet->setCellValue([1, $row], $title);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function export(): Response
    {
        $this->check();
        $this->enableCache();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('澳码软件')->setTitle($this->fileName)->setSubject($this->title);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('数据列表');
        $row = 1;
        $columnIndex = 1;
        $sheet->getRowDimension(1)->setRowHeight('60', 'pt');
        $sheet->getRowDimension(2)->setRowHeight('24', 'pt');
        // 设置标题
        if (!empty($this->title)) {
            $this->drawTitle($sheet, $row, $this->title);
            ++$row;
        }
        $summaryIndex = [];
        // 设置列
        foreach ($this->columns as $columnName) {
            if (is_array($columnName) || $columnName instanceof \ArrayAccess) {
                if (array_key_exists('summary', $columnName)) {
                    $summaryIndex[] = $columnIndex;
                }
                if (!empty($columnName['width'])) {
                    // 列宽度
                    $sheet->getColumnDimensionByColumn($columnIndex)->setWidth($columnName['width']);
                }
                $sheet->getStyle([$columnIndex, $row])->applyFromArray([
                    'borders' => [
                        'allBorders' => $this->border,
                    ],
                    'font' => ['bold' => true],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'color' => ['argb' => 'FFEEEEEE'],
                        'fillType' => 'solid',
                        'startColor' => ['argb' => 'FFEEEEEE'],
                        'endColor' => ['argb' => 'FFEEEEEE'],
                    ],
                ]);
                // 列名
                $sheet->setCellValue([$columnIndex++, $row], $columnName['title'] ?? '');
            } else {
                $sheet->setCellValue([$columnIndex++, $row], $columnName);
            }
        }
        try {
            $allData = $this->data;
            if (method_exists($this->context, 'indexAssign')) {
                $allData = $this->context->indexAssign($allData);
            }
            // 设置内容
            foreach ($allData as $index => $item) {
                $sheet->getRowDimension($index + 3)->setRowHeight('24', 'pt');
                $sheet->getStyle([1, $index + 3, count($this->columns), $index + 3])->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '00000000'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                if (method_exists($this->context, 'pageEach')) {
                    $item = $this->context->pageEach($item, $index);
                }
                ++$row;
                $columnIndex = 1;
                foreach ($this->columns as $columnKey => $columnSetting) {
                    // 序号列
                    if ('_index' == $columnKey) {
                        $cell = $sheet->getCell([$columnIndex, $row]);
                        $cell->getStyle()->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                            'fill' => [
                                'color' => ['argb' => 'FFEEEEEE'],
                                'fillType' => 'solid',
                                'startColor' => ['argb' => 'FFEEEEEE'],
                                'endColor' => ['argb' => 'FFEEEEEE'],
                            ],
                        ]);
                        $cell->setValue($index + 1);
                        ++$columnIndex;
                        continue;
                    }

                    if (
                        array_key_exists('type', $columnSetting)
                        && 'decimal' === $columnSetting['type']
                    ) {
                        $sheet->getCell([$columnIndex, $row])
                            ->getStyle()
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }

                    // 回调配置
                    if (
                        array_key_exists('callback', $columnSetting)
                        && $columnSetting['callback'] instanceof \Closure
                    ) {
                        $cell = $sheet->getCell([$columnIndex, $row]);
                        $value = call_user_func($columnSetting['callback'], $item);
                        if (isset($columnSetting['type']) && 'string' === $columnSetting['type']) {
                            $cell->setValueExplicit($value);
                        } else {
                            $cell->setValue($value);
                        }
                        ++$columnIndex;
                        continue;
                    }

                    $function = (array_key_exists('type', $columnSetting)
                        && 'string' === $columnSetting['type']) ?
                        'setValueExplicit' : 'setValue';

                    // 字典转换配置
                    if (array_key_exists('dict', $columnSetting)) {
                        $cell = $sheet->getCell([$columnIndex, $row]);
                        $val = $item[$columnKey] ?? '';
                        if ('' === $val) {
                            $cell->setValue('');
                            ++$columnIndex;
                            continue;
                        }

                        if (array_key_exists($val, $columnSetting['dict'])) {
                            $config = $columnSetting['dict'][$val];
                            if (is_array($config)) {
                                if (array_key_exists('color', $config)) {
                                    $cell->getStyle()
                                        ->getFont()
                                        ->setColor(new Color('FF' . $config['color']))
                                        ->setBold(true);
                                }
                                if (array_key_exists('text', $config)) {
                                    $cell->$function($config['text']);
                                }
                                ++$columnIndex;
                                continue;
                            }
                            if (is_string($config)) {
                                $cell->$function($config);
                                ++$columnIndex;
                                continue;
                            }
                        }
                    }

                    // 直接输出
                    $sheet->getCell([$columnIndex, $row])->$function($item[$columnKey] ?? '');
                    ++$columnIndex;
                }
            }
            if (isset($index)) {
                // 有合计行
                if (!empty($summaryIndex)) {
                    $sheet->getRowDimension($index + 4)->setRowHeight('24', 'pt');
                    foreach ($summaryIndex as $idx) {
                        $columnName = Coordinate::stringFromColumnIndex($idx);
                        $startRow = 3;
                        $endRow = $index + 3;
                        $cell = $sheet->getCell($columnName . ($index + 4));
                        $cell->getStyle()->applyFromArray([
                            'alignment' => [
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                            'font' => [
                                'bold' => true,
                            ],
                        ]);
                        $cell->getStyle()
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                        $cell->setValue('=SUM(' . $columnName . $startRow . ':' . $columnName . $endRow . ')');
                    }
                }
                $sheet->getStyle([1, 2, count($this->columns), $index + 4])->applyFromArray([
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['argb' => '00000000'],
                        ],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning($e->getFile());
            Log::warning($e->getLine());
            Log::warning($e->getTraceAsString());
            throw new \Exception($e->getMessage());
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment;filename="' . $this->fileName . '.xlsx"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}