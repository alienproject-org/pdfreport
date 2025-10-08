<?php

namespace AlienProject\PDFReport;

use PDO;
use PDOStatement;

/**
 * PDO data provider class
 * 
 * File :       DataProviderPDO.php
 * @version  	1.0.3 - 08/10/2025
 */
class DataProviderPDO implements DataProviderInterface
{
    private PDO $pdo;
    private string $query;
	private string $queryRaw = '';                  // Raw query string (with placeholders, eg. {details.id}, that will be replaced by data)
    private array $args;
    private ?PDOStatement $statement = null;
    private ?array $currentRow = null;
    private int $recordCount = 0;

    public function __construct(PDO $pdo, string $query, array $args = [])
    {
        $this->pdo = $pdo;
        $this->query = $query;
		$this->queryRaw = $query;
        $this->args = $args;
    }

    public function execute(): void
    {
        $this->reset(); // Reset before executing

        try {
            $this->statement = $this->pdo->prepare($this->query);
            if ($this->statement === false) {
                throw new \Exception('PDODateProvider: Failed to prepare statement for query: ' . $this->query);
            }

            // Bind parameters
            foreach ($this->args as $index => $arg) {
                // PDO parameters are 1-indexed for positional placeholders
                $this->statement->bindValue($index + 1, $arg);
            }

            $this->statement->execute();
            // PDOStatement::rowCount() is not reliable for SELECT statements
            // It only guarantees a count for DELETE, INSERT, or UPDATE statements.
            // For SELECT, we'll fetch all rows into an array to get the count,
            // or iterate on the statement directly if memory is a concern for large datasets.
            // For simplicity here, we'll fetch all.
            $this->statement->setFetchMode(PDO::FETCH_ASSOC); // Ensure associative array results
            
            // To get the count reliably for SELECT, you generally need to fetch all or run a separate COUNT query.
            // For large datasets, fetching all into memory is not ideal.
            // A more robust solution for large datasets would involve a separate count query:
            // $countQuery = "SELECT COUNT(*) FROM (" . $this->query . ") AS count_alias";
            // $countStmt = $this->pdo->prepare($countQuery);
            // ... bind args and execute countStmt ...
            // $this->recordCount = $countStmt->fetchColumn();

            // For now, we'll rely on the statement being iterable for `hasMoreRecords`
            // and simply let `getRecordCount` return 0 or be updated after iterating if needed.
            // Or, if you primarily use smaller result sets, you could fetchAll.
            // If you need an accurate record count *before* iterating for large datasets,
            // the separate COUNT query approach is recommended.
            //$this->recordCount = $this->statement->rowCount(); // This is often 0 for SELECT statements
			
            // Gets the record count using a separate COUNT query
			$countQuery = "SELECT COUNT(*) AS count_num_rec FROM (" . $this->query . ") AS count_alias";
            $countStmt = $this->pdo->prepare($countQuery);
			foreach ($this->args as $index => $arg) {
                $countStmt->bindValue($index + 1, $arg);
            }
            $countStmt->execute();
            $this->recordCount = $countStmt->fetchColumn();

        } catch (\PDOException $e) {
            throw new \Exception('PDODateProvider: PDO Error - ' . $e->getMessage());
        }
    }

    public function fetchNext(): ?array
    {
        if ($this->statement && ($row = $this->statement->fetch(PDO::FETCH_ASSOC))) {
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
        // This is tricky with PDO::fetch(). The best way is to try to fetch the next record.
        // However, the `fetchNext` method already advances the pointer.
        // For accurate `hasMoreRecords`, `execute` should potentially fetch everything,
        // or you need to manage the internal pointer carefully.
        // A common pattern is to fetch the first record in `execute` and then check `currentRow` in `hasMoreRecords`.
        // Given the current structure where `fetchNext` is called repeatedly:
        // After execute, if `fetchNext` returns null, then `hasMoreRecords` should be false.
        // The current implementation of PDFReportSection calls `NextRecord` after `ExecuteQuery`.
        // So, `hasMoreRecords` will simply check if the *next* fetch would yield a result.
        // Without fetching ahead, this might be less straightforward than other data providers.
        // For simplicity, let's assume `fetchNext` correctly sets `currentRow` to null on end.
        // A more robust `hasMoreRecords` might involve checking `!$this->statement->atEnd()` if PDO supported it.
        // Since it doesn't, relying on `fetchNext` to set `currentRow` to null on end of data is standard.
        return $this->currentRow !== null; // This will be true if fetchNext successfully retrieved a row
    }

    public function getRecordCount(): int
    {
        // As noted in execute(), rowCount() for SELECT is often 0.
        // If you need the exact count, you must run a separate COUNT query.
        // Otherwise, this will be 0 or an approximation depending on the driver.
        return $this->recordCount;
    }

    public function reset(): void
    {
        $this->statement = null;
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