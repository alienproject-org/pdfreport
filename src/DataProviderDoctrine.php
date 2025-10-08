<?php

namespace AlienProject\PDFReport;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use PDO;
use PDOStatement;

/**
 * Doctrine data provider class
 * 
 * File :       DataProviderDoctrine.php
 * @version     1.0.3 - 07/10/2025
 * 
 * Usage example in a controller:
 * 
 * First, insert the necessary use statements at the top of your controller file:
 *  use AlienProject\PDFReport\PDFReport;
 *  use Doctrine\DBAL\Connection;							// Used to retrieve the current database connection for use in the Doctrine Data Provider
 *  use AlienProject\PDFReport\DataProviderDoctrine;
 * 
 * In your controller action, use injected Connection to create the data provider:
 *  #[Route('/report/build', name: 'report_build')]
 *  public function build(Request $request, Connection $connection): Response
 *  {
 *      // Create the PDFReport instance
 *		$report = new PDFReport('order.xml');
 *      // Create the Doctrine data provider
 *      $ord_DataProvider = new DataProviderDoctrine(
 *							    $connection,  
 *							    'SELECT * FROM vorders WHERE orderNumber=?',
 *							    [ 103 ]);
 *      // Assign the data provider to the report
 *      $report->SetSection('ord', $ord_DataProvider);
 *		// Build the report
 *		$report->BuildReport();
 *  }
 */
class DataProviderDoctrine implements DataProviderInterface
{
    private Connection $connection;
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
     * @param EntityManagerInterface|Connection $connectionOrEntityManager The Doctrine EntityManager or Connection
     * @param string $query The SQL query string
     * @param array $args Query parameters
     */
    public function __construct($connectionOrEntityManager, string $query = '', array $args = [])
    {
        // Accept either EntityManager or Connection
        if ($connectionOrEntityManager instanceof EntityManagerInterface) {
            $this->connection = $connectionOrEntityManager->getConnection();
        } elseif ($connectionOrEntityManager instanceof Connection) {
            $this->connection = $connectionOrEntityManager;
        } else {
            throw new \InvalidArgumentException('DataProviderDoctrine: First argument must be an EntityManagerInterface or Connection instance');
        }
        
        // Get the underlying PDO instance from Doctrine DBAL
        $this->pdo = $this->connection->getNativeConnection();
        
        // For Doctrine DBAL 3.x+, getNativeConnection() might return a driver connection wrapper
        // If it's not a PDO instance, try to get it via getWrappedConnection()
        if (!$this->pdo instanceof PDO) {
            if (method_exists($this->pdo, 'getWrappedConnection')) {
                $this->pdo = $this->pdo->getWrappedConnection();
            }
        }
        
        if (!$this->pdo instanceof PDO) {
            throw new \Exception('DataProviderDoctrine: Unable to retrieve PDO instance from Doctrine connection');
        }
        
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
                throw new \Exception('DataProviderDoctrine: Failed to prepare statement for query: ' . $this->query);
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
            throw new \Exception('DataProviderDoctrine: PDO Error - ' . $e->getMessage());
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