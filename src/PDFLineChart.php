<?php

namespace AlienProject\PDFReport;

enum ChartStyleType : int
{
    case Line = 0;
    case Area = 1;
}

/**
 * Class that generates a line graph
 * 
 * File :       PDFLineChart.php
 * @version  	1.0.4 - 15/11/2025
 */
class PDFLineChart {
    /**
     * Private/public properties automatically generated and initialized by the constructor
     * $x1, $y1, $x2, $y2 
     * minValue             float, minimum Y value of the chart
     * maxValue             float, maximum Y value of the chart (0.0=auto scale ref to max item value)
     * legendSettings       PDFGraphLegendSettings object
     * $dataItems           Array of PDFChartItem object (each element of the line chart)
     */
    
    // Public properties
    public ?PDFGraphLegend $legend = null;                  // null=no legend
    public ?PDFFontSettings $axisFont = null;               // Font settings for the axis labels
    public ?PDFLineSettings $axisLine = null;
    public ?PDFFillSettings $symbolFill = null;             // Fill settings for the symbol used to mark each data point (null=no fill)
    public ?PDFLineSettings $symbolLine = null;             // Border line settings for the symbol used to mark each data point (null=no border)
    public ChartStyleType $style = ChartStyleType::Line;
    public float $opacity = 1.0;                            // Current opacity/transparent (alpha color component setting, range: 0.0 .. 1.0)
    
    /**
    * Line Chart class constructor
    *
    * @param float $x1          The x-coordinate of the upper-left corner
    * @param float $y1          The y-coordinate of the upper-left corner
    * @param float $x2          The x-coordinate of the lower-right corner
    * @param float $y2          The y-coordinate of the lower-right corner
    * @param array $dataItems                       The PDFChartItem array with label, value, color and other properties for each point of the line chart
    * @param float $minValue                        Minimum value of the chart
    * @param float $maxValue                        Maximum value of the chart (0.0=auto scale ref to max item value)
    * @param string $title                          Title of the chart  
    * @param PDFFontSettings $titleFont             Font settings for the title
    * @param array $measures                        List of measures (Each measurement is a line on the graph)
    * @param PDFLegendSettings $legendSettings      Legend settings
    * @param float $ticksCount                      Number of ticks on the axis with numeric values ​​(Y-axis if isVertical=true)
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                public float $minValue, public float $maxValue, 
                                public string $title, public PDFFontSettings $titleFont, public array $measures, 
                                public PDFLegendSettings $legendSettings, 
                                private array $dataItems, private $ticksCount = 5) 
    {
        // Auto-create-initialize all private properties : $x1 ... $dataItems
        
        // Calcolate line points (X,Y) of the chart
        //$this->calculateLinePoints();     

        // Calculate legend settings (auto size)
        if ($legendSettings->isVisible) {
            $this->legend = new PDFGraphLegend($legendSettings, null, $measures);
        }

        // Validate args
        if ($ticksCount < 2) {
            $this->ticksCount = 2;
        }
    }

    /**
     * Calculate the point (X,Y) of each element of the graph
     * The index parameter is the numeric index of the series (0..N-1) to use
     */
    private function calculateLinePoints(int $index): void {
        // Calculate the max value
        $max = 0.0;
        foreach ($this->dataItems as $item) {
            $itemValue = $item->getValue($index);
            if ($itemValue > $max) {
                $max = $itemValue;
            }
        }
        if ($this->maxValue == 0.0) {
            // If maxValue is not set, use the max value (auto scale)
            $this->maxValue = $max;
        }

        // Calculate position (X,Y) of each data point
        $totalLines = count($this->dataItems);
        $availableWidth = ($this->x2 - $this->x1);
        $lineGap = ($availableWidth / $totalLines);
        foreach ($this->dataItems as $item_index => $item) {
            $itemValue = $item->getValue($index);
            // X position
            $item->x1 = $this->x1 + ($item_index * $lineGap) + ($lineGap / 2);
            $item->x2 = $item->x1;
            // Y position
            $item->percentage = $itemValue / $this->maxValue;               // Calculates the percentage of the value compared to the full scale
            $height = ($this->y2 - $this->y1) * $item->percentage;          // Calculate the height of the line
            $item->y1 = $this->y2 - $height;                                // Set the Y line position
            if ($item->y1 < $this->y1) {
                // It does not go out of the space assigned to the graph if the data goes out of maximum scale
                $item->y1 = $this->y1;
            }
            $item->y2 = $item->y1;
        }
    }
    
    private function renderMeasureAsLine($report, $i, $measure)
    {
        // Draw chart line A-B for measure $i
        for ($t = 0; $t < count($this->dataItems) - 1; $t++) {
            // Point A
            $lineItem1 = $this->dataItems[$t];
            $x1 = $lineItem1->x1;
            $y1 = $lineItem1->y1;
            // Point B 
            $lineItem2 = $this->dataItems[$t + 1];
            $x2 = $lineItem2->x1;
            $y2 = $lineItem2->y1;
            // Check for values not null, not 0. 
            if (empty($lineItem1->getValue($i))) {
                // Use point B
                $x1 = $x2;
                $y1 = $y2;
            }
            if (empty($lineItem2->getValue($i))) {
                // Use point A
                $x2 = $x1;
                $y2 = $y1;
            }
            if (empty($lineItem1->getValue($i)) && empty($lineItem2->getValue($i))) continue;   // No line/point to draw
            // Draw line (or point)
            $report->PdfLine($x1, $y1, $x2, $y2, $measure->line);
            // Point symbol
            switch ($measure->symbol->shape) {
                case 'C':   // Circle
                    $report->PdfCircle($x1, $y1, $measure->symbol->size / 2.0, 0, 360, $measure->symbol->line, $measure->symbol->fill);
                    if ($t == count($this->dataItems) - 2) {
                        // Draw symbol circle for last point
                        $report->PdfCircle($x2, $y2, $measure->symbol->size / 2.0, 0, 360, $measure->symbol->line, $measure->symbol->fill);
                    }
                    break;
                case 'S':   // Square
                    $halfSize = $measure->symbol->size / 2.0;
                    $report->PdfRectangle($x1 - $halfSize, $y1 - $halfSize, $x1 + $halfSize, $y1 + $halfSize, 0, '1111', $measure->symbol->line, $measure->symbol->fill);
                    if ($t == count($this->dataItems) - 2) {
                        // Draw symbol square for last point
                        $report->PdfRectangle($x2 - $halfSize, $y2 - $halfSize, $x2 + $halfSize, $y2 + $halfSize, 0, '1111', $measure->symbol->line, $measure->symbol->fill);
                    }
                    break;
            }
        }
    }

    private function renderMeasureAsArea($report, $i, $measure)
    {
        // Draw chart area for measure $i
        $report->SetOpacity($this->opacity, true, false);
        $coord = [];
        for ($t = 0; $t <= count($this->dataItems) - 1; $t++) {
            // Point
            $lineItem = $this->dataItems[$t];
            $x1 = $lineItem->x1;
            $y1 = $lineItem->y1;
            if ($t == 0) {
                // First polygon point
                $coord[] = $x1;
                $coord[] = $this->y2;
            }
            // Creates coordinate array for the polygon
            $coord[] = $x1;
            $coord[] = $y1;    
            if ($t == count($this->dataItems) - 1) {
                // Last polygon point
                $coord[] = $x1;
                $coord[] = $this->y2;
            }
        }
        $lineStyle = $measure->line->GetStyle();
        $colArray = $measure->symbol->fill->GetStartColor();
        $report->pdf->Polygon($coord, 'DF', [ 'all' => $lineStyle ], $colArray);
        $report->ResetOpacity();
    }

    public function render(?PDFReport $report = null) : void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
        
        if ($this->axisLine == null) {
            // Use default line settings for axis
            $this->axisLine = $report->GetDefaultLine();
        }

        // Background rectangle
        //$backFill = new PDFFillSettings('S', 'EEEEEE');
        //$report->PdfRectangle($this->x1, $this->y1, $this->x2, $this->y2, 0, '0000', null, $backFill);

        foreach ($this->measures as $i => $measure) {
            // Calculate line points for the current $i measure
            $this->calculateLinePoints($i);
            if ($this->style == ChartStyleType::Area)
                // Area style
                $this->renderMeasureAsArea($report, $i, $measure);
            else
                // Line - Default style
                $this->renderMeasureAsLine($report, $i, $measure);
        }

        // Title
        if ($this->title != '') {
            $cellHeightRatio = $pdf->getCellHeightRatio();
            $singleLineHeight = $this->titleFont->size * $cellHeightRatio;
            $report->pdfBox($this->x1, $this->y1 - $singleLineHeight, $this->x2, $this->y1, $this->title, $this->titleFont, 'C', 'M', 0);
        }

        // Draw X-Y axis
        if ($this->axisFont != null) {
            // Use custom axis font
            $axisFont = $this->axisFont;
        } else {
            // Use default axis font
            $axisFont = new PDFFontSettings('helvetica', '', 8, '000000');
        }
        
        // Draw Y axis with labels (left side)
        $axisSettings = new PDFAxisSettings($this->x1 - 15.0, $this->y1, $this->x1, $this->y2, $this->minValue, $this->maxValue, '', $axisFont, true, true, $this->axisLine);
        $axisSettings->ticksCount = $this->ticksCount;
        $axis = new PDFGraphAxis($axisSettings);            
        $axis->render($report);
        // Draw X axis with labels (bottom side)
        $axisSettings = new PDFAxisSettings($this->x1, $this->y2, $this->x2, $this->y2 + 15.0, $this->minValue, $this->maxValue, '', $axisFont, true, false, $this->axisLine, $this->dataItems);
        $axis = new PDFGraphAxis($axisSettings);
        $axis->render($report);
    
        // Print legend
        if ($this->legend != null)
            $this->legend->render($report);
    }

    /**
     * Return an array with the data items of the chart
     *
     * @return array
     */
    public function getDataItems(): array {
        return $this->dataItems;
    }  
}

