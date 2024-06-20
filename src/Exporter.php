<?php

namespace aoma;

use aoma\exporter\PhpSheet;
use aoma\exporter\XlsWriter;

/**
 * Data Exporter
 *
 * @author Aomaosft <fonqing@gmail.com>
 * @version 1.0.0
 *
 * @example
 * $exporter = Exporter::loadDriver('php_spreadsheet');
 * $exporter->setColumns([
 *     '_index' => ['title' => 'Idx', width => 5],
 *     'name' => ['title' => 'Name', width => 20],
 *     'score' => [
 *         'title' => 'Score', // Label in table header column
 *         'width' => 20, // width of column in unit ft
 *         'type' => 'string', // string,decimal,number
 *         'summary' => true // enable total sum row,
 *         'color' => 'FF6600',
 *     ],
 *     'gender' => [
 *         'title' => 'Gender',
 *         'width' => 10,
 *         'dict' => [
 *             'M' => 'Male',
 *             'F' => 'Female',
 *         ]
 *     ],
 *    'computed' => [
 *         'title' => 'Computed',
 *         'width' => 10,
 *         'callback' => function($row) {
 *              return ($row['score'] ?? 1 ) * 2;
 *        }
*      ]
 * ])->setTitle('Course List')
 *          ->setFileName('Course_List')
 *          ->setControllerContext($controller)
 *          ->setDataQuery($query)
 *          ->export();
 */
abstract class Exporter
{
    private static array $drivers = [
        'php_spreadsheet' => PhpSheet::class,
        'xls_writer' => XlsWriter::class,
    ];

    abstract function setColumns(array $columns): static;
    abstract function setTitle(string $title): static;
    abstract function setFileName(string $name): static;
    abstract function setControllerContext($controller): static;
    abstract function setDataQuery($data): static;
    abstract function export(): void;
    /**
     * @throws \Exception
     */
    public static function loadDriver(string $driverName): PhpSheet|XlsWriter
    {
        if(isset(self::$drivers[$driverName])) {
            return new self::$drivers[$driverName];
        }
        throw new \Exception("Driver not found: $driverName");
    }
}