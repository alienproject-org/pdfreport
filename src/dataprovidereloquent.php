<?php
// file : DataProviderEloquent.php

namespace AlienProject\PDFReport;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\LazyCollection;

/**
 * Eloquent Data Provider (for Laravel)
 * For Eloquent, you'll pass an Eloquent Builder instance.
 * 
 * @version  	1.0.2 - 04/10/2025
 * 
 * Usage example:
 * Using existing Eloquent Builder 
 *  $builder = User::where('active', true)->orderBy('name');
 *  $dataProvider = new DataProviderEloquent($builder);
 *
 * Using query raw
 *  $dataProvider = DataProviderEloquent::fromRawQuery(
 *   'mysql', 
 *   'SELECT * FROM users WHERE active = ?', 
 *   [true]
 *  );
 *
 * Standard usage:
 *  $dataProvider->execute();
 *   while ($row = $dataProvider->fetchNext()) {
 *      // Processa $row...
 *   }
 */
class DataProviderEloquent implements DataProviderInterface
{
    private Builder $queryBuilder;
    private string $query = '';                     // SQL query string
    private string $queryRaw = '';                  // Raw query string (with placeholders, eg. {details.id}, that will be replaced by data)
    private array $bindings = [];                   // Query bindings/parameters
    private ?LazyCollection $results = null;
    private ?\Iterator $iterator = null;
    private int $recordCount = 0;
    private ?array $currentRow = null;
    private bool $executed = false;

    public function __construct(Builder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
        
        // Store the original SQL query and bindings
        $this->updateQueryInfo();
    }

    /**
     * Alternative constructor that accepts a raw SQL query
     */
    public static function fromRawQuery(string $connectionName, string $sql, array $bindings = []): self
    {
        // Create a basic query builder for the connection
        $connection = \Illuminate\Support\Facades\DB::connection($connectionName);
        $queryBuilder = $connection->query();
        
        $instance = new self($queryBuilder);
        $instance->query = $sql;
        $instance->queryRaw = $sql;
        $instance->bindings = $bindings;
        
        return $instance;
    }

    /**
     * Updates internal query information from the current QueryBuilder
     */
    private function updateQueryInfo(): void
    {
        try {
            $this->query = $this->queryBuilder->toSql();
            $this->queryRaw = $this->query;
            $this->bindings = $this->queryBuilder->getBindings();
        } catch (\Exception $e) {
            // If we can't get query info, set defaults
            $this->query = '';
            $this->queryRaw = '';
            $this->bindings = [];
        }
    }

    public function execute(): void
    {
        $this->reset(); // Reset before executing

        try {
            // Clone the query builder to avoid modifying the original
            $queryBuilderForExecution = clone $this->queryBuilder;
            
            // Use cursor() for large datasets to avoid loading all into memory
            // cursor() returns a LazyCollection which implements Iterator
            $this->results = $queryBuilderForExecution->cursor();
            $this->iterator = $this->results->getIterator();

            // Get count using a separate count query for accuracy
            $this->recordCount = $this->getRecordCountFromBuilder();

            $this->executed = true;

        } catch (\Exception $e) {
            throw new \Exception('EloquentDataProvider: Error executing query - ' . $e->getMessage());
        }
    }

    /**
     * Gets the record count using a separate count query
     */
    private function getRecordCountFromBuilder(): int
    {
        try {
            // Clone the query builder for count to avoid affecting the original
            $countBuilder = clone $this->queryBuilder;
            
            // Use count() method which is optimized for counting
            return $countBuilder->count();
            
        } catch (\Exception $e) {
            // If count fails, log error and return 0
            error_log('EloquentDataProvider: Failed to get record count - ' . $e->getMessage());
            return 0;
        }
    }

    public function fetchNext(): ?array
    {
        if (!$this->executed) {
            throw new \Exception('EloquentDataProvider: Query not executed. Call execute() first.');
        }

        if ($this->iterator && $this->iterator->valid()) {
            $model = $this->iterator->current();
            $this->iterator->next();
            
            // Convert Eloquent model to associative array
            if ($model) {
                $this->currentRow = $model->toArray();
                return $this->currentRow;
            }
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
        
        return $this->iterator && $this->iterator->valid();
    }

    public function getRecordCount(): int
    {
        return $this->recordCount;
    }

    public function reset(): void
    {
        $this->results = null;
        $this->iterator = null;
        $this->currentRow = null;
        $this->recordCount = 0;
        $this->executed = false;
    }

    public function getQuery(): string
    {
        // Update query info in case the builder was modified
        if (!$this->executed) {
            $this->updateQueryInfo();
        }
        return $this->query;
    }

    public function setQuery(string $query): void
    {
        $this->query = $query;
        $this->executed = false; // Mark as not executed since query changed
        
        // Note: Setting raw SQL on an Eloquent Builder is complex
        // This method is mainly for compatibility with the interface
        // In practice, you'd modify the QueryBuilder directly or use fromRawQuery()
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
     * Gets the query bindings/parameters
     */
    public function getBindings(): array
    {
        // Update bindings in case the builder was modified
        if (!$this->executed) {
            $this->updateQueryInfo();
        }
        return $this->bindings;
    }

    /**
     * Sets query bindings (mainly for raw queries)
     */
    public function setBindings(array $bindings): void
    {
        $this->bindings = $bindings;
        $this->executed = false; // Mark as not executed since bindings changed
    }

    /**
     * Gets the underlying Eloquent Builder
     */
    public function getQueryBuilder(): Builder
    {
        return $this->queryBuilder;
    }

    /**
     * Adds a where condition to the query builder
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        $this->queryBuilder->where($column, $operator, $value, $boolean);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds an order by clause to the query builder
     */
    public function orderBy($column, $direction = 'asc'): self
    {
        $this->queryBuilder->orderBy($column, $direction);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a limit to the query builder
     */
    public function limit(int $limit): self
    {
        $this->queryBuilder->limit($limit);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds an offset to the query builder
     */
    public function offset(int $offset): self
    {
        $this->queryBuilder->offset($offset);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a select clause to the query builder
     */
    public function select($columns = ['*']): self
    {
        $this->queryBuilder->select($columns);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a join to the query builder
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): self
    {
        $this->queryBuilder->join($table, $first, $operator, $second, $type, $where);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a left join to the query builder
     */
    public function leftJoin($table, $first, $operator = null, $second = null): self
    {
        $this->queryBuilder->leftJoin($table, $first, $operator, $second);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a group by clause to the query builder
     */
    public function groupBy(...$groups): self
    {
        $this->queryBuilder->groupBy(...$groups);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Adds a having clause to the query builder
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        $this->queryBuilder->having($column, $operator, $value, $boolean);
        $this->executed = false; // Mark as not executed since query changed
        return $this;
    }

    /**
     * Gets the SQL representation of the query with bindings
     */
    public function toSql(): string
    {
        return $this->queryBuilder->toSql();
    }

    /**
     * Gets the SQL query with bound values (for debugging)
     */
    public function toRawSql(): string
    {
        $sql = $this->queryBuilder->toSql();
        $bindings = $this->queryBuilder->getBindings();
        
        // Replace ? placeholders with actual values (for debugging purposes)
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'{$binding}'" : $binding;
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        
        return $sql;
    }
}

?>