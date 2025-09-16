<?php
// file : dataproviderdoctrine.php

namespace AlienProject\PDFReport;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;

/**
 * Doctrine Data Provider (for Symfony)
 * For Doctrine, you'll typically use a QueryBuilder or directly fetch entities from a repository.
 * 
 * @version  	1.0.1 - 16/09/2025
 */
class DataProviderDoctrine implements DataProviderInterface
{
    private EntityManagerInterface $entityManager;
    private QueryBuilder $queryBuilder;
    private string $query = '';                     // DQL query string
    private string $queryRaw = '';                  // Raw query string (with placeholders, eg. {details.id}, that will be replaced by data)
    private array $parameters = [];                 // Query parameters
    private ?\Iterator $results = null;
    private int $recordCount = 0;
    private ?array $currentRow = null;
    private bool $executed = false;

    public function __construct(QueryBuilder $queryBuilder, EntityManagerInterface $entityManager = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->entityManager = $entityManager ?? $queryBuilder->getEntityManager();
        
        // Store the original DQL query
        $this->query = $queryBuilder->getDQL();
        $this->queryRaw = $this->query;
        
        // Get parameters from QueryBuilder
        $this->parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $this->parameters[$parameter->getName()] = $parameter->getValue();
        }
    }

    /**
     * Alternative constructor that accepts a DQL string directly
     */
    public static function fromDQL(EntityManagerInterface $entityManager, string $dql, array $parameters = []): self
    {
        $queryBuilder = $entityManager->createQueryBuilder();
        // We'll set the DQL directly in the instance
        $instance = new self($queryBuilder, $entityManager);
        $instance->query = $dql;
        $instance->queryRaw = $dql;
        $instance->parameters = $parameters;
        return $instance;
    }

    public function execute(): void
    {
        $this->reset(); // Reset before executing

        try {
            // Create query from current DQL string
            if (!empty($this->query)) {
                $query = $this->entityManager->createQuery($this->query);
            } else {
                $query = $this->queryBuilder->getQuery();
            }

            // Set parameters
            foreach ($this->parameters as $name => $value) {
                $query->setParameter($name, $value);
            }

            // Use an iterable result to avoid loading all records into memory for large datasets
            $this->results = $query->iterate(Query::HYDRATE_ARRAY);

            // Get count using a separate count query for accuracy
            $this->recordCount = $this->getCountFromQuery($query);

            $this->executed = true;

        } catch (\Exception $e) {
            throw new \Exception('DoctrineDataProvider: Error executing query - ' . $e->getMessage());
        }
    }

    /**
     * Gets the record count using a separate COUNT query
     */
    private function getCountFromQuery(Query $originalQuery): int
    {
        try {
            // Try to create a count query from the QueryBuilder if available
            if ($this->queryBuilder->getDQL()) {
                $countQueryBuilder = clone $this->queryBuilder;
                $rootAliases = $countQueryBuilder->getRootAliases();
                
                if (!empty($rootAliases)) {
                    // Clear select, orderBy, and groupBy for count query
                    $countQueryBuilder->select('COUNT(DISTINCT ' . $rootAliases[0] . '.id)')
                                     ->orderBy() // Clear order by
                                     ->groupBy(); // Clear group by (if any)
                    
                    $countQuery = $countQueryBuilder->getQuery();
                    
                    // Set the same parameters
                    foreach ($this->parameters as $name => $value) {
                        $countQuery->setParameter($name, $value);
                    }
                    
                    return (int) $countQuery->getSingleScalarResult();
                }
            }

            // Fallback: try to modify the DQL string for count (basic approach)
            if (!empty($this->query)) {
                // This is a simplified approach - in production you might want a more sophisticated DQL parser
                $countDql = $this->createCountDqlFromOriginal($this->query);
                $countQuery = $this->entityManager->createQuery($countDql);
                
                foreach ($this->parameters as $name => $value) {
                    $countQuery->setParameter($name, $value);
                }
                
                return (int) $countQuery->getSingleScalarResult();
            }

        } catch (\Exception $e) {
            // If count query fails, we'll return 0 and log the error
            error_log('DoctrineDataProvider: Failed to get record count - ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Creates a COUNT DQL from the original DQL (basic implementation)
     */
    private function createCountDqlFromOriginal(string $originalDql): string
    {
        // This is a very basic implementation
        // In a production environment, you'd want a more robust DQL parser
        
        // Find the FROM clause
        if (preg_match('/FROM\s+(\w+)\s+(\w+)/i', $originalDql, $matches)) {
            $entityClass = $matches[1];
            $alias = $matches[2];
            
            // Extract WHERE clause if present
            $whereClause = '';
            if (preg_match('/WHERE\s+(.+?)(?:ORDER\s+BY|GROUP\s+BY|$)/is', $originalDql, $whereMatches)) {
                $whereClause = 'WHERE ' . trim($whereMatches[1]);
            }
            
            return "SELECT COUNT({$alias}.id) FROM {$entityClass} {$alias} {$whereClause}";
        }
        
        // Fallback
        return "SELECT COUNT(*) FROM (" . $originalDql . ")";
    }

    public function fetchNext(): ?array
    {
        if (!$this->executed) {
            throw new \Exception('DoctrineDataProvider: Query not executed. Call execute() first.');
        }

        if ($this->results && $this->results->valid()) {
            // Current()[0] because iterate returns an array with index 0 being the entity data
            $this->currentRow = $this->results->current()[0] ?? $this->results->current();
            $this->results->next();
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
        if (!$this->executed) {
            return false;
        }
        
        return $this->results && $this->results->valid();
    }

    public function getRecordCount(): int
    {
        return $this->recordCount;
    }

    public function reset(): void
    {
        $this->results = null;
        $this->currentRow = null;
        $this->recordCount = 0;
        $this->executed = false;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
        $this->executed = false; // Mark as not executed since query changed
    }

    public function getQueryRaw(): string
    {
        return $this->queryRaw;
    }

    public function setQueryRaw(string $queryRaw): void
    {
        $this->queryRaw = $queryRaw;
    }

    /**
     * Gets the query parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Sets query parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
        $this->executed = false; // Mark as not executed since parameters changed
    }

    /**
     * Sets a single parameter
     */
    public function setParameter(string $name, $value): void
    {
        $this->parameters[$name] = $value;
        $this->executed = false; // Mark as not executed since parameter changed
    }

    /**
     * Gets the underlying QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * Gets the EntityManager
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}

?>