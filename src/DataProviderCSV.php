<?php

namespace AlienProject\PDFReport;

/**
 * CSV file data provider class: each line of the file is a data row, the first line contains the field names.
 *
 * The file is read once, when the data provider is created. The "query" is an optional filter (see DataProviderArray),
 * used to link a detail section to its master section, eg. 'orderNumber={ord.orderNumber}'.
 * A relative file path is resolved from the current working directory (as the template file).
 *
 * Options:
 *      delimiter       Field separator: ',' ';' "\t" '|' ... (default: detected from the first line)
 *      enclosure       Field enclosure character (default '"')
 *      encoding        Encoding of the file, converted to UTF-8 (default 'UTF-8'; eg. 'Windows-1252' for a CSV saved by Excel)
 *      header          true (default): the first line contains the field names; false: the fields are named col1, col2, ...
 *      columns         Field names (array), they replace the names of the first line
 *      decimalComma    true: numbers with decimal comma (eg. 1.234,56 or 12,5) are converted to 1234.56 and 12.5
 *                      so that they can be formatted and compared as numbers (default false)
 *      trim            true (default): spaces at the beginning and at the end of the values are removed
 *
 * Example:
 *      $report->SetSection('ord', new DataProviderCSV('orders.csv'));
 *      $report->SetSection('ord_det', new DataProviderCSV('order_lines.csv', 'orderNumber={ord.orderNumber}', [ 'delimiter' => ';', 'decimalComma' => true ]));
 *
 * File :       DataProviderCSV.php
 * @version  	1.0.12 - 05/10/2026
 */
class DataProviderCSV extends DataProviderArray
{
    public function __construct(string $file, string $filter = '', array $options = [])
    {
        parent::__construct($this->readCsv($file, $options), $filter);
    }

    // Returns the rows of the CSV file as associative arrays
    private function readCsv(string $file, array $options): array
    {
        $enclosure = (string)($options['enclosure'] ?? '"');
        $encoding = strtoupper((string)($options['encoding'] ?? 'UTF-8'));
        $header = (bool)($options['header'] ?? true);
        $columns = $options['columns'] ?? null;
        $decimalComma = (bool)($options['decimalComma'] ?? false);
        $trim = (bool)($options['trim'] ?? true);

        $content = $this->readDataFile($file);
        if ($encoding != 'UTF-8' && $encoding != 'UTF8') {
            $converted = function_exists('mb_convert_encoding') ? @mb_convert_encoding($content, 'UTF-8', $encoding) : @iconv($encoding, 'UTF-8//TRANSLIT', $content);
            if ($converted === false) {
                throw new \Exception('DataProviderCSV: unable to convert the file from ' . $encoding . ' to UTF-8 [' . $file . ']');
            }
            $content = $converted;
        }
        $delimiter = (string)($options['delimiter'] ?? $this->detectDelimiter($content, $enclosure));

        // fgetcsv() on a memory stream: fields enclosed in quotes can contain delimiters and line breaks
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $names = is_array($columns) ? array_values($columns) : null;
        $rows = [];
        $first = true;
        while (($values = fgetcsv($stream, 0, $delimiter, $enclosure, '')) !== false) {
            if ($values === [ null ]) continue;                     // Empty line
            if ($trim) $values = array_map(fn($value) => trim((string)$value), $values);
            if ($first) {
                $first = false;
                if ($header) {
                    if ($names === null) $names = $values;
                    continue;
                }
            }
            if ($names === null) {
                $names = [];
                for ($t = 1; $t <= count($values); $t++) $names[] = 'col' . $t;
            }
            $row = [];
            foreach ($names as $index => $name) {
                $value = (string)($values[$index] ?? '');           // Missing values: empty string, extra values: ignored
                if ($decimalComma) $value = $this->convertDecimalComma($value);
                $row[$name] = $value;
            }
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    // Field separator of the first line: the most used among , ; tab | (outside the quotes)
    private function detectDelimiter(string $content, string $enclosure): string
    {
        $line = strtok($content, "\r\n");
        if ($line === false) return ',';
        if ($enclosure != '') $line = preg_replace('/' . preg_quote($enclosure, '/') . '.*?' . preg_quote($enclosure, '/') . '/', '', $line);
        $best = ',';
        $bestCount = 0;
        foreach ([ ',', ';', "\t", '|' ] as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }
        return $best;
    }

    // 1.234,56 -> 1234.56 and 12,5 -> 12.5 (only values with a decimal comma: "1.234" or "10.100" are not changed)
    private function convertDecimalComma(string $value): string
    {
        if (preg_match('/^[+-]?(\d{1,3}(\.\d{3})+|\d+),\d+$/', $value)) {
            return str_replace([ '.', ',' ], [ '', '.' ], $value);
        }
        return $value;
    }
}
