<?php

namespace AlienProject\PDFReport;

/**
 * JSON file data provider class: the data rows are an array of objects in a JSON file.
 *
 * The file is read once, when the data provider is created. The "query" is an optional filter (see DataProviderArray),
 * used to link a detail section to its master section, eg. 'orderNumber={ord.orderNumber}'.
 * A relative file path is resolved from the current working directory (as the template file).
 *
 * Options:
 *      path        Position of the rows array in the JSON document, keys separated by dots (default '': the document is the array).
 *                  Eg. with { "data": { "orders": [ {...}, {...} ] } } use 'data.orders'.
 *                  A single object (not an array) is a single data row.
 *
 * Nested values (objects or arrays inside a row) are converted to their JSON text: a field used in the template must be a scalar value.
 *
 * Example:
 *      $report->SetSection('ord', new DataProviderJSON('orders.json', '', [ 'path' => 'orders' ]));
 *      $report->SetSection('ord_det', new DataProviderJSON('orders.json', 'orderNumber={ord.orderNumber}', [ 'path' => 'lines' ]));
 *
 * File :       DataProviderJSON.php
 * @version  	1.0.12 - 05/10/2026
 */
class DataProviderJSON extends DataProviderArray
{
    public function __construct(string $file, string $filter = '', array $options = [])
    {
        parent::__construct($this->readJson($file, (string)($options['path'] ?? '')), $filter);
    }

    // Returns the rows of the JSON file as associative arrays
    private function readJson(string $file, string $path): array
    {
        $data = json_decode($this->readDataFile($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('DataProviderJSON: invalid JSON in [' . $file . '], ' . json_last_error_msg());
        }
        if ($path != '') {
            foreach (explode('.', $path) as $key) {
                if (!is_array($data) || !array_key_exists($key, $data)) {
                    throw new \Exception('DataProviderJSON: path [' . $path . '] not found in [' . $file . ']');
                }
                $data = $data[$key];
            }
        }
        if (!is_array($data)) {
            throw new \Exception('DataProviderJSON: the data' . ($path != '' ? ' at path [' . $path . ']' : '') . ' of [' . $file . '] must be an array of objects');
        }
        if (!self::isList($data)) $data = [ $data ];                // A single object: one data row
        $rows = [];
        foreach ($data as $item) {
            if (!is_array($item) || self::isList($item) && count($item) > 0) {
                throw new \Exception('DataProviderJSON: the data' . ($path != '' ? ' at path [' . $path . ']' : '') . ' of [' . $file . '] must be an array of objects');
            }
            $row = [];
            foreach ($item as $name => $value) {
                $row[$name] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // Same as array_is_list() (PHP 8.1+)
    private static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }
}
