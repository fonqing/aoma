<?php

namespace aoma\exporter;

use aoma\Exporter;
use aoma\fast\BaseController;
use aoma\StringPlus;
use Exception;
use support\Log;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;
class XlsWriter extends Exporter
{
    private string $tmpPath = '';

    private string $tmpName = '';

    /**
     * @var Excel
     */
    private Excel $object;

    /**
     * @var resource
     */
    private $handler;

    private array $columns = [];
    private string $fileName = '';
    private string $title = '';

    /**
     * @var BaseController
     */
    private $context;

    private $data;

    public static array $static = [];

    public function __construct(array $columns = [])
    {
        $this->tmpName = md5(uniqid() . microtime(true)) . '.xlsx';
        $this->tmpPath = public_path('/uploads/download');
        $this->object = (new Excel([
            'path' => $this->tmpPath,
        ]))->fileName($this->tmpName);
        $this->handler = $this->object->getHandle();
        if (!empty($columns)) {
            $this->columns = $columns;
        }
    }

    public function setColumns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function setFileName(string $name): static
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

    public static function stringFromColumnIndex($index): string
    {
        return Excel::stringFromColumnIndex($index);
    }

    private function formatStyle(array $style)
    {
        $format = new Format($this->handler);
        if (isset($style['border'])) {
            $format = $format->border($style['border']);
        }
        if (isset($style['fontSize'])) {
            $format = $format->fontSize($style['fontSize']);
        }
        if (isset($style['align'])) {
            $format = $format->align(...$style['align']);
        }
        if (isset($style['background']) && $style['background']) {
            $format = $format->background($style['background']);
        }
        if (isset($style['bold'])) {
            $format = $format->bold();
        }
        if (isset($style['color']) && $style['color']) {
            $format = $format->fontColor("0x{$style['color']}");
        }
        if (isset($style['format']) && $style['format']) {
            $format = $format->number($style['format']);
        }
        return $format->toResource();
    }

    /**
     * @throws Exception
     */
    private function check(): void
    {
        if (empty($this->columns)) {
            throw new Exception('字段配置为空,无法导出');
        }
        if (empty($this->data)) {
            throw new Exception('数据查询为空,无法导出');
        }
        if (empty($this->title)) {
            throw new Exception('未设置导出表格标题');
        }
        if (empty($this->fileName)) {
            throw new Exception('未设置导出文件名');
        }
    }

    /**
     * @param  mixed      $row
     * @param  mixed      $title
     * @throws Exception
     */
    private function drawTitle($row, $title): void
    {
        if (!is_string($title)) {
            throw new Exception('标题必须是字符串类型');
        }
        $start = self::stringFromColumnIndex(0);
        $end = self::stringFromColumnIndex(count($this->columns) - 1);
        $this->object = $this->object->mergeCells(
            $start . $row . ':' . $end . $row,
            $title
        );
        $this->object = $this->object->setRow(
            $start . $row,
            60,
            $this->formatStyle([
                'fontSize' => 18,
                'bold'     => true,
                'align'    => [
                    Format::FORMAT_ALIGN_CENTER,
                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                ],
            ])
        );
    }

    /**
     * @throws Exception
     */
    public function export(): void
    {
        $this->check();
        $row = 1;
        $columnIndex = 1;
        $this->object = $this->object->setRow('A2:A2', 24);
        // 设置标题
        if (!empty($this->title)) {
            $this->drawTitle($row, $this->title);
            ++$row;
        }
        $summaryIndex = [];
        $this->object = $this->object->freezePanes(1, 0);
        $this->object = $this->object->freezePanes(2, 0);
        // 设置列
        foreach ($this->columns as $columnName) {
            $indexName = self::stringFromColumnIndex($columnIndex - 1);
            if (is_array($columnName) || $columnName instanceof \ArrayAccess) {
                if (array_key_exists('summary', $columnName)) {
                    $summaryIndex[] = [
                        'index' => $columnIndex,
                        'name'  => $indexName,
                    ];
                }
                if (!empty($columnName['width'])) {
                    // 列宽度
                    $this->object = $this->object->setColumn(
                        $indexName . ':' . $indexName,
                        floatval($columnName['width'])
                    );
                }
                // 列名
                $this->object = $this->object->insertText(
                    $row - 1, $columnIndex - 1,
                    $columnName['title'] ?? '', null, $this->formatStyle([
                    'bold'  => true,
                    'align' => [
                        Format::FORMAT_ALIGN_CENTER,
                        Format::FORMAT_ALIGN_VERTICAL_CENTER,
                    ],
                    'background' => 0xEEEEEE,
                    'border'     => Format::BORDER_THIN,
                ]));
            } else {
                $this->object = $this->object->insertText(
                    $row - 1,
                    $columnIndex - 1, $columnName, null,
                    $this->formatStyle([
                        'bold'  => true,
                        'align' => [
                            Format::FORMAT_ALIGN_CENTER,
                            Format::FORMAT_ALIGN_VERTICAL_CENTER,
                        ],
                        'background' => 0xEEEEEE,
                        'border'     => Format::BORDER_THIN,
                    ])
                );
            }
            ++$columnIndex;
        }
        try {
            $allData = $this->data->select();
            if (method_exists($this->context, 'indexAssign')) {
                $allData = $this->context->indexAssign($allData);
            }
            // 设置内容
            foreach ($allData as $index => $item) {
                if (method_exists($this->context, 'pageEach')) {
                    $item = $this->context->pageEach($item, $index);
                }
                ++$row;
                $columnIndex = 1;
                foreach ($this->columns as $columnKey => $columnSetting) {
                    $format = null;
                    // 序号列
                    if ('_index' == $columnKey) {
                        $this->object = $this->object->insertText(
                            $row - 1,
                            $columnIndex - 1,
                            $index + 1,
                            null,
                            $this->formatStyle([
                                'border' => Format::BORDER_THIN,
                                'align'  => [
                                    Format::FORMAT_ALIGN_CENTER,
                                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                                ],
                                'background' => 0xF7F7F7,
                            ])
                        );
                        ++$columnIndex;
                        continue;
                    }

                    if ((array_key_exists('type', $columnSetting)
                            && 'decimal' === $columnSetting['type']) || (array_key_exists('summary', $columnSetting)
                            && $columnSetting['summary'])) {
                        $format = '#,##0.00';
                    }

                    // 回调配置
                    if (array_key_exists('callback', $columnSetting)
                        && $columnSetting['callback'] instanceof \Closure) {
                        $value = call_user_func_array(
                            $columnSetting['callback'],
                            [$item, $columnIndex]
                        );
                        $color = 0x000000;
                        $align = Format::FORMAT_ALIGN_CENTER;
                        if (is_array($value)) {
                            if (isset($value['color'])) {
                                $color = $value['color'];
                            }
                            if (isset($value['align'])) {
                                switch ($value['align']) {
                                    case 'left':
                                        $align = Format::FORMAT_ALIGN_LEFT;
                                        break;
                                    case 'right':
                                        $align = Format::FORMAT_ALIGN_RIGHT;
                                        break;
                                    case 'center':
                                    default:
                                        break;
                                }
                            }
                            $value = $value['value'] ?? 'Err##';
                        }
                        if ($format) {
                            $value = floatval($value);
                            $align = Format::FORMAT_ALIGN_RIGHT;
                        }
                        $this->object = $this->object->insertText(
                            $row - 1,
                            $columnIndex - 1, $value, $format, $this->formatStyle([
                            'border' => Format::BORDER_THIN,
                            'color'  => $color,
                            'align'  => [$align, Format::FORMAT_ALIGN_VERTICAL_CENTER],
                        ]));
                        ++$columnIndex;
                        continue;
                    }

                    // 字典转换配置
                    if (array_key_exists('dict', $columnSetting)) {
                        $val = $item[$columnKey] ?? '';
                        if ('' === $val) {
                            $this->object = $this->object->insertText(
                                $row - 1,
                                $columnIndex - 1,
                                '',
                                null,
                                $this->formatStyle([
                                    'border' => Format::BORDER_THIN,
                                ]));
                            ++$columnIndex;
                            continue;
                        }

                        if (array_key_exists($val, $columnSetting['dict'])) {
                            $config = $columnSetting['dict'][$val];
                            if (is_array($config)) {
                                $style = false;
                                if (array_key_exists('color', $config)) {
                                    $style = true;
                                }
                                if (array_key_exists('text', $config)) {
                                    $this->object = $this->object->insertText(
                                        $row - 1,
                                        $columnIndex - 1,
                                        $config['text'], null, $style ? $this->formatStyle([
                                        'color'  => $config['color'] ?? null,
                                        'bold'   => true,
                                        'border' => Format::BORDER_THIN,
                                        'align'  => [
                                            Format::FORMAT_ALIGN_CENTER,
                                            Format::FORMAT_ALIGN_VERTICAL_CENTER,
                                        ],
                                    ]) : null);
                                }
                                ++$columnIndex;
                                continue;
                            }
                            if (is_string($config)) {
                                $this->object = $this->object->insertText(
                                    $row - 1,
                                    $columnIndex - 1,
                                    $config,
                                    null,
                                    $this->formatStyle([
                                        'border' => Format::BORDER_THIN,
                                        'align'  => [
                                            Format::FORMAT_ALIGN_CENTER,
                                            Format::FORMAT_ALIGN_VERTICAL_CENTER,
                                        ],
                                    ]));
                                ++$columnIndex;
                                continue;
                            }
                        }
                    }

                    $val = $format ? floatval($item[$columnKey] ?? 0) : $item[$columnKey] ?? '';
                    // 直接输出
                    $this->object = $this->object->insertText(
                        $row - 1,
                        $columnIndex - 1,
                        $val,
                        $format,
                        $this->formatStyle([
                            'border' => Format::BORDER_THIN,
                        ]));
                    ++$columnIndex;
                }
            }
            if (isset($index)) {
                // 有合计行
                if (!empty($summaryIndex)) {
                    $this->object = $this->object->setRow('A' . ($index + 4), 24);
                    foreach ($summaryIndex as $row) {
                        $startRow = 2;
                        $endRow = $index + 3;
                        $this->object = $this->object->insertFormula(
                            $index + 3,
                            $row['index'] - 1,
                            '=SUM(' . $row['name'] . $startRow . ':' . $row['name'] . $endRow . ')', $this->formatStyle([
                            'border'     => Format::BORDER_THIN,
                            'align'      => [Format::FORMAT_ALIGN_VERTICAL_CENTER],
                            'background' => 0xF7F7F7,
                            'bold'       => true,
                            'format'     => '#,##0.00',
                        ]));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning($e->getFile());
            Log::warning($e->getLine());
            Log::warning($e->getTraceAsString());
            throw new Exception($e->getMessage());
        }

        $filePath = $this->object->output();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $this->tmpName . '.xlsx"');
        header('Content-Length: ' . filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        ob_clean();
        flush();
        if (false === copy($filePath, 'php://output')) {
            throw new Exception('导出失败');
        }
        file_exists($filePath) && unlink($filePath);
    }

    /**
     * 初始化
     *
     * 本方法一般搭配下面的一系列镜头方法导出，与上面的方法为两种不同的导出形式
     * 不通用且不可混用
     *
     * @return void
     */
    public static function init()
    {
        self::$static['tmpName'] = md5(uniqid() . microtime(true)) . '.xlsx';
        self::$static['tmpPath'] = public_path('/uploads/download');
        self::$static['object'] = (new Excel([
            'path' => self::$static['tmpPath'],
        ]))->fileName(self::$static['tmpName']);
        self::$static['handler'] = self::$static['object']->getHandle();
    }

    /**
     * 样式设置
     *
     * @param  array    $style
     * @return resource
     */
    public static function style(array $style)
    {
        $format = new Format(self::$static['handler']);
        if (isset($style['border'])) {
            $format = $format->border($style['border']);
        }
        if (isset($style['fontSize'])) {
            $format = $format->fontSize($style['fontSize']);
        }
        if (isset($style['align'])) {
            $format = $format->align(...$style['align']);
        }
        if (isset($style['background'])) {
            if(is_string($style['background'])) {
                $format = $format->background("0x{$style['background']}");
            }elseif(is_int($style['background'])) {
                $format = $format->background($style['background']);
            }
        }
        if (isset($style['bold'])) {
            $format = $format->bold();
        }
        if (isset($style['color'])) {
            if(is_string($style['color'])) {
                $format = $format->fontColor("0x{$style['color']}");
            }elseif(is_int($style['color'])) {
                $format = $format->fontColor($style['color']);
            }
        }
        if (isset($style['format'])) {
            $format = $format->number($style['format']);
        }
        return $format->toResource();
    }

    /**
     * 绘制title
     *
     * @param  string $title
     * @param  int    $count
     * @param  int    $offset
     * @return void
     */
    public static function title(string $title, int $count, int &$offset): void
    {
        $offset = $offset + 1;
        $start = self::stringFromColumnIndex(0);
        $end = self::stringFromColumnIndex($count);
        self::$static['object'] = self::$static['object']->mergeCells(
            $start . $offset . ':' . $end . $offset,
            $title
        );
        self::$static['object'] = self::$static['object']->setRow(
            $start . $offset,
            50,
            self::style([
                'fontSize' => 18,
                'bold'     => true,
                'align'    => [
                    Format::FORMAT_ALIGN_LEFT,
                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                ],
            ])
        );
    }

    /**
     * 输出header
     *
     * @param  array  $columns
     * @param  string $title
     * @param  int    $offset
     * @return void
     */
    public static function header(array $columns, string $title, int &$offset): void
    {
        self::title($title, count($columns), $offset);
        self::$static['object'] = self::$static['object']->insertText(
            $offset,
            0,
            '序号',
            null,
            self::style([
                'border'     => Format::BORDER_MEDIUM,
                'bold'       => true,
                'background' => 0xEEEEEE,
                'align'      => [
                    Format::FORMAT_ALIGN_CENTER,
                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                ],
            ]));
        self::$static['object'] = self::$static['object']->setColumn('A1:A1', floatval(8));
        foreach ($columns as $columnIndex => $column) {
            $indexName = self::stringFromColumnIndex($columnIndex + 1);
            self::$static['object'] = self::$static['object']->setColumn(
                $indexName . '1:' . $indexName . '1',
                floatval(($column['width'] ?? 200) / 10) // 前端宽度和后端宽度适配处理
            );
            self::$static['object'] = self::$static['object']->insertText(
                $offset,
                $columnIndex + 1,
                $column['label'],
                null,
                self::style([
                    'border'     => Format::BORDER_MEDIUM,
                    'bold'       => true,
                    'background' => 0xEEEEEE,
                    'align'      => [
                        Format::FORMAT_ALIGN_CENTER,
                        Format::FORMAT_ALIGN_VERTICAL_CENTER,
                    ],
                ]));
        }
        $offset = $offset + 1;
    }

    /**
     * 输出数据到电子表格
     *
     * @param  array $data
     * @param  array $columns
     * @param  int   $offset
     * @return void
     */
    public static function data(array $data, array $columns, int &$offset): void
    {
        $sum = [];
        foreach ($data as $index => $row) {
            $columnIndex = 0;
            // 序号列
            self::$static['object'] = self::$static['object']->insertText(
                $offset,
                $columnIndex,
                $index + 1,
                null,
                self::style([
                    'border' => Format::BORDER_THIN,
                    'align'  => [
                        Format::FORMAT_ALIGN_CENTER,
                        Format::FORMAT_ALIGN_VERTICAL_CENTER,
                    ],
                ]));
            $columnIndex = $columnIndex + 1;
            // 每一列逐列赋值
            foreach ($columns as $column) {
                $key = self::getKey($column);
                // 数据表直接key
                if (isset($row[$key])) {
                    // 数字列自动合计
                    if ((array_key_exists('summary', $column) && $column['summary'])
                        || (array_key_exists('type', $column) && 'decimal' == $column['type'])) {
                        // 提前生成合计公式
                        $cellName = self::stringFromColumnIndex($columnIndex);
                        if (!array_key_exists($cellName, $sum)) {
                            $sum[$cellName] = [
                                'column' => $columnIndex,
                                'value'  => '=SUM(' . $cellName . ($offset + 1) . ':' . $cellName . ($offset + count($data)) . ')',
                            ];
                        }
                        // 插入单元格数字内容
                        self::$static['object'] = self::$static['object']->insertText(
                            $offset,
                            $columnIndex,
                            floatval($row[$key]),
                            '#,##0.00',
                            self::style([
                                'border' => Format::BORDER_THIN,
                                'align'  => [
                                    Format::FORMAT_ALIGN_RIGHT,
                                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                                ],
                            ]));
                    } else {
                        self::$static['object'] = self::$static['object']->insertText(
                            $offset,
                            $columnIndex,
                            $row[$key],
                            null,
                            self::style([
                                'border' => Format::BORDER_THIN,
                                'align'  => [
                                    Format::FORMAT_ALIGN_LEFT,
                                    Format::FORMAT_ALIGN_VERTICAL_CENTER,
                                ],
                            ])
                        );
                    }
                } else {
                    // 关联键的值获取处理
                    $value = self::getValue($key, $row);
                    self::$static['object'] = self::$static['object']->insertText(
                        $offset,
                        $columnIndex,
                        $value,
                        null,
                        self::style([
                            'border' => Format::BORDER_THIN,
                            'align'  => [
                                Format::FORMAT_ALIGN_LEFT,
                                Format::FORMAT_ALIGN_VERTICAL_CENTER,
                            ],
                        ]));
                }
                $columnIndex = $columnIndex + 1;
            }
            $offset = $offset + 1;
        }
        // 合计列输出公式 展示合计
        foreach ($sum as $row) {
            self::$static['object'] = self::$static['object']->insertFormula(
                $offset,
                $row['column'],
                $row['value'],
                self::style([
                    'border'     => Format::BORDER_THIN,
                    'background' => 0xEEEEEE,
                    'format'     => '#,##0.00',
                    'fontSize'   => 12,
                    'bold'       => true,
                    'align'      => [
                        Format::FORMAT_ALIGN_RIGHT,
                        Format::FORMAT_ALIGN_VERTICAL_CENTER,
                    ],
                ]));
        }
        $offset = $offset + 1;
    }

    /**
     * 输出导出文件
     *
     * @return mixed
     */
    public static function output()
    {
        $filePath = self::$static['object']->output();
        self::$static = [];
        return $filePath;
    }

    /**
     * 获取列配置的键
     *
     * 兼容二级单位统计表和内部银行余额表两种column配置
     *
     * @param  array $column
     * @return string
     */
    private static function getKey(array $column): string
    {
        return $column['key'] ?? $column['prop'];
    }

    /**
     * 关联数据访问时根据key获取值
     *
     * @param  string $columnKey
     * @param  array  $row
     * @return string
     */
    private static function getValue(string $columnKey, array $row): string
    {
        $value = '';
        // 多字段拼合与单字段统一处理
        if (StringPlus::str_contains($columnKey, '|')) {
            $keys = explode('|', $columnKey);
        } else {
            $keys = [$columnKey];
        }
        // 逐级逐个获取值
        foreach ($keys as $key) {
            $fields = StringPlus::str_contains($key, '.') ? explode('.', $key) : [$key];
            switch (count($fields)) {
                // 类似 row.money
                case 1: $value .= $row[$fields[0]] ?? '';
                    break;
                // 类似 row.user.name
                case 2: $value .= $row[$fields[0]][$fields[1]] ?? '';
                    break;
                // 类似 row.user.info.age
                case 3: $value .= $row[$fields[0]][$fields[1]][$fields[2]] ?? '';
                    break;
                // 类似 row.user.info.address.city
                case 4: $value .= $row[$fields[0]][$fields[1]][$fields[2]][$fields[3]] ?? '';
                    break;
            }
        }
        return $value;
    }
}