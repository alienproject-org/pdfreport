<?php

namespace AlienProject\PDFReport;

/**
 * PDFDatalist Class
 *
 * File :       PDFDatalist.php
 * @version  	1.0.2 - 04/10/2025
 */
class PDFDatalist
{
    public string $id = '';                             // Datalist Id

    private ?DataProviderInterface $dataProvider;       // Data provider interface
    public ?array $row = null;                          // Current row data (associative array : fieldname => value) or NULL (end of data)
    
    private int $recIndex = 0;                          // Current record index in the results array [0..n-1]
    private int $recCount = 0;                          // Total number of records in the results array
    private bool $endOfData = true;

    /**
     * @param string $id                                Datalist ID
     * @param DataProviderInterface $dataProvider       An instance of a class implementing DataProviderInterface
     */
    public function __construct(string $id, ?DataProviderInterface $dataProvider)
    {
        $this->id = $id;
        $this->dataProvider = $dataProvider;
        PDFLog::Write("Datalist($id)-Construct");      // Assuming PDFLog is a utility class
    }

    public function Reset(): void
    {
        $this->row = null;
        $this->recIndex = 0;
        $this->endOfData = true;
        if ($this->dataProvider != null)
            $this->dataProvider->reset();                           // Reset the data provider as well
        PDFLog::Write("Datalist(" . $this->id . ")-Reset");
    }

    /*
    public function getCurrentRow()
    {
        if ($this->dataProvider == null) 
            return [];
        return $this->dataProvider->getCurrentRow();
    }
    */

    public function NextRecord(): void
    {
        if ($this->dataProvider == null) return;
        
        PDFLog::Write("Datalist(" . $this->id . ")-NextRecord-Start");

        $this->row = $this->dataProvider->fetchNext();
        if ($this->row !== null) {
            $this->recIndex++;
            $this->endOfData = false;
            PDFLog::Write("Datalist(" . $this->id . ")-NextRecord:recIndex=[" . $this->recIndex . "]");
        } else {
            // No more data from the data provider
            $this->row = null;
            $this->endOfData = true;
            PDFLog::Write("Datalist(" . $this->id . ")-NextRecord:End Of Data (from data provider)");
        }
        PDFLog::Write("Datalist(" . $this->id . ")-NextRecord-End");
    }

    public function ExecuteQuery(): int
    {
        if ($this->dataProvider == null) return 0;

        PDFLog::Write("Datalist(" . $this->id . ")-ExecuteQuery-Start");
        if ($this->row !== null) {
            PDFLog::Write("Datalist(" . $this->id . ")-ExecuteQuery-End:Query already executed (data available)");
            return $this->dataProvider->getRecordCount();       // Return current record count if already fetched
        }

        $this->Reset();
        $this->dataProvider->execute();
        $this->recIndex = 0;                // Reset index for internal tracking after execution
        $this->NextRecord();                // Fetch the first record (NextRecord will set $this->row)
        $this->endOfData = !$this->dataProvider->hasMoreRecords(); // Update endOfData based on the data provider
        $this->recCount = $this->dataProvider->getRecordCount();

        PDFLog::Write("Datalist(" . $this->id . ")-ExecuteQuery-End:recCount=[" . $this->dataProvider->getRecordCount() . "]");
        return $this->dataProvider->getRecordCount();
    }

    public function EndOfData(): bool
    {
        return $this->endOfData;
    }

    public function getQuery(): string
    {
        if ($this->dataProvider == null) return '';
        return $this->dataProvider->getQuery();
    }

    public function setQuery(string $query): void
    {
        if ($this->dataProvider == null) return;
        $this->dataProvider->setQuery($query);
    }

    public function getQueryRaw(): string
    {
        if ($this->dataProvider == null) return '';
        return $this->dataProvider->getQueryRaw();
    }

    public function setQueryRaw(string $queryRaw): void
    {
        if ($this->dataProvider == null) return;
        $this->dataProvider->setQueryRaw($queryRaw);
    }

}