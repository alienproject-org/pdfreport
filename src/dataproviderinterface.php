<?php

namespace AlienProject\PDFReport;

/**
 * Generic data provider interface
 * 
 * File :       DataProviderInterface.php
 * @version  	1.0.2 - 04/10/2025
 */
interface DataProviderInterface
{
    /**
     * Executes the data retrieval logic.
     */
    public function execute(): void;

    /**
     * Fetches the next record.
     *
     * @return array|null The current record as an associative array, or null if no more records.
     */
    public function fetchNext(): ?array;

    /**
     * Gtes the current data record.
     *
     * @return array|null   The current record as an associative array, or null if no data loaded.
     */
    public function getCurrentRow(): ?array;
    
    /**
     * Checks if there are more records available.
     *
     * @return bool True if there are more records, false otherwise.
     */
    public function hasMoreRecords(): bool;

    /**
     * Gets the total number of records.
     *
     * @return int The total number of records.
     */
    public function getRecordCount(): int;

    /**
     * Resets the data provider to its initial state.
     */
    public function reset(): void;

    public function getQuery(): string;
    
    public function setQuery(string $query): void;
    
    public function getQueryRaw(): string;
    
    public function setQueryRaw(string $queryRaw): void;
    
}