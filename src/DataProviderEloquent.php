<?php

namespace AlienProject\PDFReport;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use PDO;
use PDOStatement;

/**
 * Eloquent data provider class
 * 
 * File :       DataProviderEloquent.php
 * @version     1.0.3 - 07/10/2025
 * 
 * Usage example in a controller:
 * 
 * First, insert the necessary use statements at the top of your controller file:
 *  use Illuminate\Http\Request;
 *  use Illuminate\Support\Facades\DB; 
 *  use AlienProject\PDFReport\PDFReport;
 *  use AlienProject\PDFReport\DataProviderEloquent;
 * 
 * In your controller action, In the controller action, retrieve the current database connection from the DB facades:
 *  public function build()
 *  {
 *      // Create the PDFReport instance
 *      $report = new PDFReport('order.xml');
 *      // Get the current database connection
 *      $connection = DB::connection();
 *      // Create the Eloquent data provider
 *      $ord_DataProvider = new DataProviderEloquent(
 *                               $connection,
 *                              'SELECT * FROM vorders WHERE orderNumber=?',
 *                              [ 103 ]);
 *      // Assign the data provider to the report
 *      $report->SetSection('ord', $ord_DataProvider);
 *      // Build the report
 *      $report->BuildReport();
 *  }
 * 
 */
class DataProviderEloquent implements DataProviderInterface
{
    private ConnectionInterface $connection;
    private PDO $pdo;
    private string $query;
    private string $queryRaw = '';                  // Raw query string (with placeholders, eg. {details.id}, that will be replaced by data)
    private array $args;
    private ?PDOStatement $statement = null;
    private ?array $currentRow = null;
    private int $recordCount = 0;

    /**
     * Constructor
     * 
     * @param ConnectionInterface|null $connection The Eloquent connection (if null, uses default)
     * @param string $query The SQL query string
     * @param array $args Query parameters
     */
    public function __construct(?ConnectionInterface $connection = null, string $query = '', array $args = [])
    {
        // Use provided connection or get the default one from Capsule
        $this->connection = $connection ?? Capsule::connection();
        
        // Get the underlying PDO instance from Eloquent
        $this->pdo = $this->connection->getPdo();
        
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
                throw new \Exception('DataProviderEloquent: Failed to prepare statement for query: ' . $this->query);
            }

            // Bind parameters
            foreach ($this->args as $index => $arg) {
                // PDO parameters are 1-indexed for positional placeholders
                $this->statement->bindValue($index + 1, $arg);
            }

            $this->statement->execute();
            $this->statement->setFetchMode(PDO::FETCH_ASSOC); // Ensure associative array results
            
            // Gets the record count using a separate COUNT query
            $countQuery = "SELECT COUNT(*) AS count_num_rec FROM (" . $this->query . ") AS count_alias";
            $countStmt = $this->pdo->prepare($countQuery);
            foreach ($this->args as $index => $arg) {
                $countStmt->bindValue($index + 1, $arg);
            }
            $countStmt->execute();
            $this->recordCount = $countStmt->fetchColumn();

        } catch (\PDOException $e) {
            throw new \Exception('DataProviderEloquent: PDO Error - ' . $e->getMessage());
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
        return $this->currentRow !== null;
    }

    public function getRecordCount(): int
    {
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