<?php

namespace AlienProject\PDFReport;

/**
 * PDFReportSection class
 *
 * This class defines a section of the report that can be cycled by the report generation engine to perform the same processing on a set of data of the same type
 * (for example the lines of an invoice)
 *
 * File :       PDFReportSection.php
 * @version  	1.0.2 - 04/10/2025
 */
class PDFReportSection
{
    public string $id = '';                             // Section Id

    private ?DataProviderInterface $dataProvider;       // Data provider interface
    public ?array $row = null;                          // Current row data (associative array : fieldname => value) or NULL (end of data)

    public $y_start = 0.0;                              // Y (mm) start position on the page 
    public $row_height = 0.0;                           // Row height (mm)
    public $y_end = 296.0;                              // Y (mm) end position on the page (296 mm : A4 page format). If [(row_height * lineIndex) >= y_end] a page break is performed.
    public $rows_count = 1;
    public ?PDFPageSettings $page = null;               // Page settings
    
    private int $recIndex = 0;                          // Current record index in the results array [0..n-1]
    private int $recCount = 0;                          // Total number of records in the results array
    private int $lineIndex = 0;                         // Count each line as it is printed. If it exceeds rows_count, a page break is performed.
    private bool $pageBreak = false;
    private bool $endOfData = true;
    private int $pageIndex = 0; // Current page index

    /**
     * @param string $id                            The section ID.
     * @param DataProviderInterface $dataProvider   An instance of a class implementing DataProviderInterface.
     * @param PDFPageSettings|null $page            Page settings.
     */
    public function __construct(string $id, ?DataProviderInterface $dataProvider, ?PDFPageSettings $page = null)
    {
        $this->id = $id;
        $this->dataProvider = $dataProvider;
        $this->page = $page;
        PDFLog::Write("ReportSection($id)-Construct"); // Assuming PDFLog is a utility class
    }

    public function GetPageIndex(): int
    {
        return $this->pageIndex;
    }

    public function Reset(): void
    {
        $this->row = null;
        $this->recIndex = 0;
        $this->lineIndex = 0;
        $this->pageBreak = false;
        $this->endOfData = true;
        $this->pageIndex = 0;
        if ($this->dataProvider != null)
            $this->dataProvider->reset();                           // Reset the data provider as well
        PDFLog::Write("ReportSection(" . $this->id . ")-Reset");
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
        
        PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord-Start");

        $this->pageBreak = false;
        $this->row = $this->dataProvider->fetchNext();
        if ($this->row !== null) {
            if (($this->lineIndex == 0 && $this->recIndex == 0) || ($this->lineIndex == 1 && $this->recIndex > $this->lineIndex)) {
                $this->pageIndex++;
            }
            $this->recIndex++;
            $this->lineIndex++;             // 1 .. N (line index in the current page)
            $this->endOfData = false;
            PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord:recIndex=[" . $this->recIndex . "]");

            if ($this->page == null) {
                if ($this->CurrentY() >= $this->y_end || $this->lineIndex >= $this->rows_count) {
                    // Do page break
                    $this->lineIndex = 1;
                    $this->pageBreak = true;
                }
                PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord:pageBreak=[" . ($this->pageBreak ? "true]" : "false]"));
            } else {
                PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord:pageBreak=[skip]");
            }
        } else {
            // No more data from the data provider
            $this->row = null;
            $this->endOfData = true;
            $this->lineIndex = 0;
            $this->pageBreak = false;
            PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord:End Of Data (from data provider)");
        }
        PDFLog::Write("ReportSection(" . $this->id . ")-NextRecord-End");
    }

    public function ExecuteQuery(): int
    {
        if ($this->dataProvider == null) return 0;

        PDFLog::Write("ReportSection(" . $this->id . ")-ExecuteQuery-Start");
        if ($this->row !== null) {
            PDFLog::Write("ReportSection(" . $this->id . ")-ExecuteQuery-End:Query already executed (data available)");
            return $this->dataProvider->getRecordCount(); // Return current record count if already fetched
        }

        $this->Reset();
        $this->dataProvider->execute();
        $this->recIndex = 0;            // Reset index for internal tracking after execution
        $this->NextRecord();            // Fetch the first record (NextRecord will set $this->row)
        $this->endOfData = !$this->dataProvider->hasMoreRecords(); // Update endOfData based on the data provider
        $this->recCount = $this->dataProvider->getRecordCount();

        PDFLog::Write("ReportSection(" . $this->id . ")-ExecuteQuery-End:recCount=[" . $this->dataProvider->getRecordCount() . "]");
        return $this->dataProvider->getRecordCount();
    }

    public function ResetPageBreak(): void
    {
        $this->lineIndex = 1; // 1 .. N (line index in the current page)
        $this->pageBreak = false;
        PDFLog::Write("ReportSection(" . $this->id . ")-ResetPageBreak");
    }

    public function OffsetY(): float
    {
        $index = ($this->lineIndex > 0) ? $this->lineIndex - 1 : 0;
        return ($index * $this->row_height);
    }

    public function CurrentY(): float
    {
        return ($this->y_start + $this->OffsetY());
    }

    public function EndOfData(): bool
    {
        return $this->endOfData;
    }

    public function EndOfPage(): bool
    {
        $endOfPage = ($this->pageBreak || $this->endOfData);
        return $endOfPage;
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