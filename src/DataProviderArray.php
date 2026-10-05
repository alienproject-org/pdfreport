<?php

namespace AlienProject\PDFReport;

/**
 * Array data provider class: the data rows are a PHP array (eg. data read from an API or built by the code).
 * Base class of the file data providers (DataProviderCSV, DataProviderJSON, DataProviderXML).
 *
 * The "query" is an optional filter, used to link a detail section to its master section:
 *      field=value [AND field2=value2 ...]
 * Placeholders are replaced before the filter is applied, eg. 'orderNumber={ord.orderNumber}' becomes 'orderNumber=10100'.
 * Values can be enclosed in single or double quotes; two numeric values are compared as numbers, otherwise as strings.
 * An empty filter returns all the rows.
 *
 * Example:
 *      $report->SetSection('ord', new DataProviderArray($orders));
 *      $report->SetSection('ord_det', new DataProviderArray($orderLines, 'orderNumber={ord.orderNumber}'));
 *
 * File :       DataProviderArray.php
 * @version  	1.0.12 - 05/10/2026
 */
class DataProviderArray implements DataProviderInterface
{
    protected array $rows;                          // All the data rows (each row is an associative array)
    protected string $query = '';                   // Filter with the placeholders replaced (eg. orderNumber=10100)
    protected string $queryRaw = '';                // Raw filter string (with placeholders, eg. {ord.orderNumber}, that will be replaced by data)
    protected array $filteredRows = [];             // Rows returned by the last execute()
    protected int $position = 0;
    protected ?array $currentRow = null;

    public function __construct(array $rows, string $filter = '')
    {
        $this->rows = array_values($rows);
        $this->query = $filter;
        $this->queryRaw = $filter;
    }

    public function execute(): void
    {
        $this->reset();
        $conditions = $this->parseFilter($this->query);
        foreach ($this->rows as $row) {
            if (!is_array($row)) {
                throw new \Exception($this->className() . ': each data row must be an associative array');
            }
            if ($this->rowMatches($row, $conditions)) {
                $this->filteredRows[] = $row;
            }
        }
    }

    public function fetchNext(): ?array
    {
        $this->currentRow = $this->filteredRows[$this->position] ?? null;
        if ($this->currentRow !== null) {
            $this->position++;
        }
        return $this->currentRow;
    }

    public function getCurrentRow(): ?array
    {
        return $this->currentRow;
    }

    public function hasMoreRecords(): bool
    {
        // Same behavior of the other data providers: true while fetchNext() returns a row
        return $this->currentRow !== null;
    }

    public function getRecordCount(): int
    {
        return count($this->filteredRows);
    }

    public function reset(): void
    {
        $this->filteredRows = [];
        $this->position = 0;
        $this->currentRow = null;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function getQueryRaw(): string
    {
        return $this->queryRaw;
    }

    public function setQueryRaw(string $queryRaw): void
    {
        $this->queryRaw = $queryRaw;
    }

    // Returns the content of a data file (used by the file data providers), the UTF-8 BOM is removed
    protected function readDataFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \Exception($this->className() . ': data file not found or not readable [' . $file . ']');
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new \Exception($this->className() . ': unable to read the data file [' . $file . ']');
        }
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    // Short class name for the error messages (eg. DataProviderCSV)
    protected function className(): string
    {
        $pos = strrpos(static::class, '\\');
        return $pos === false ? static::class : substr(static::class, $pos + 1);
    }

    // Returns the filter conditions as [ [field, value], ... ]
    protected function parseFilter(string $filter): array
    {
        $conditions = [];
        $filter = trim($filter);
        if ($filter == '') return $conditions;
        foreach (preg_split('/\s+AND\s+/i', $filter) as $condition) {
            $pos = strpos($condition, '=');
            if ($pos === false) {
                throw new \Exception($this->className() . ': invalid filter condition [' . $condition . '], expected field=value');
            }
            $field = trim(substr($condition, 0, $pos));
            $value = trim(substr($condition, $pos + 1));
            if (strlen($value) >= 2 && ($value[0] == "'" || $value[0] == '"') && substr($value, -1) == $value[0]) {
                $value = substr($value, 1, -1);
            }
            $conditions[] = [ $field, $value ];
        }
        return $conditions;
    }

    protected function rowMatches(array $row, array $conditions): bool
    {
        foreach ($conditions as [ $field, $value ]) {
            if (!array_key_exists($field, $row)) return false;
            $rowValue = $row[$field];
            if (is_numeric($rowValue) && is_numeric($value)) {
                if ((float)$rowValue != (float)$value) return false;
            } elseif ((string)$rowValue !== $value) {
                return false;
            }
        }
        return true;
    }
}
