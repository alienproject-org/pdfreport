<?php

namespace AlienProject\PDFReport;

use mysqli;
use mysqli_stmt;
use mysqli_result;

/**
 * MySQLi data provider interface
 * 
 * @version  	1.0.2 - 04/10/2025
 */
class DataProviderMySQLi implements DataProviderInterface
{
    private mysqli $mysqli;
    private string $query = '';
    private string $queryRaw = '';                  // Raw query string (with placeholders, eg. {details.id}, that will be replaced by data)
    private array $args = [];
    private ?mysqli_result $result = null;
    private ?array $currentRow = null;
    private int $recordCount = 0;

    public function __construct(mysqli $mysqli, string $query, array $args = [])
    {
        $this->mysqli = $mysqli;
        $this->query = $query;
        $this->queryRaw = $query;
        $this->args = $args;
    }

    public function execute(): void
    {
        $this->reset(); // Reset before executing

        $stmt = $this->mysqli->prepare($this->query);
        if ($stmt === false) {
            throw new \Exception('MySQLiDataProvider: Failed to prepare statement for query: ' . $this->query . ' Error: ' . $this->mysqli->error);
        }

        if (!empty($this->args)) {
            // Determine types for bind_param
            // MySQLi bind_param requires types string (s, i, d, b)
            $types = '';
            foreach ($this->args as $arg) {
                if (is_int($arg)) {
                    $types .= 'i';
                } elseif (is_float($arg)) {
                    $types .= 'd';
                } elseif (is_string($arg)) {
                    $types .= 's';
                } else {
                    $types .= 'b'; // blob for others or assume string
                }
            }
            
            // Use splat operator to pass arguments by reference for bind_param
            $bindArgs = array_merge([$types], $this->args);
            $refArgs = [];
            foreach ($bindArgs as $key => $value) {
                $refArgs[$key] = &$bindArgs[$key];
            }

            if (!call_user_func_array([$stmt, 'bind_param'], $refArgs)) {
                throw new \Exception('MySQLiDataProvider - Failed to bind parameters: ' . $stmt->error);
            }
        }

        $stmt->execute();
        $this->result = $stmt->get_result(); // This will return a mysqli_result object
        
        if ($this->result === false) {
            throw new \Exception('MySQLiDataProvider - Failed to get result set: ' . $stmt->error);
        }

        $this->recordCount = $this->result->num_rows; // num_rows is reliable for SELECT
    }

    public function fetchNext(): ?array
    {
        if ($this->result && ($row = $this->result->fetch_assoc())) {
            $this->currentRow = $row;
            return $this->currentRow;
        }
        $this->currentRow = null;
        return null;
    }

    public function getCurrentRow(): ?array
    {
        return $this->currentRow;
    }

    public function hasMoreRecords(): bool
    {
        // For mysqli_result, after fetching, you can check if the current row is not null
        // or if there are more rows available.
        // A common pattern is to simply check if `fetch_assoc()` returned a row.
        return $this->currentRow !== null; // True if fetchNext successfully retrieved a row
    }

    public function getRecordCount(): int
    {
        return $this->recordCount;
    }

    public function reset(): void
    {
        if ($this->result) {
            $this->result->free();      // Free the result set memory
        }
        $this->result = null;
        $this->currentRow = null;
        $this->recordCount = 0;
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

}