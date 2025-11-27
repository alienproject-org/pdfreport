<?php

namespace AlienProject\PDFReport;

/**
 * Class to generate a bar chart
 * 
 * File :       PDFBarChart.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFBarChart {
    /**
     * Private/public properties automatically generated and initialized by the constructor
     * $x1, $y1, $x2, $y2 
     * isVertical           bool, true=vertical, false=horizontal
     * minValue             float, minimum Y value of the chart
     * maxValue             float, maximum Y value of the chart (0.0=auto scale ref to max item value)
     * barSize              float, size of each bar (width for vertical chart, height for horizontal chart). 0=auto size
     * barMargin            float, margin between bars (in mm, 0=no margin)
     * legendSettings       PDFGraphLegendSettings object
     * $dataItems           Array of PDFChartItem object (each element is a separate bar)
     */
    
    // Public properties
    public ?PDFGraphLegend $legend = null;          // null=no legend
    public ?PDFFontSettings $axisFont = null;       // Font settings for the axis labels
    public ?PDFLineSettings $axisLine = null;
    public bool $showValuesOnDataPoint = false;
    // Private properties
    private bool $YAutoScale = true;
    private bool $AutoBarSize = true;

    /**
    * Bar Chart class constructor
    *
    * @param float $x1          The x-coordinate of the upper-left corner
    * @param float $y1          The y-coordinate of the upper-left corner
    * @param float $x2          The x-coordinate of the lower-right corner
    * @param float $y2          The y-coordinate of the lower-right corner
    * @param array $dataItems   The PDFChartItem array with label, value, color and other properties for each bar
    * @param bool $isVertical   true=vertical bar chart, false=horizontal bar chart
    * @param float $minValue    Minimum value of the chart
    * @param float $maxValue    Maximum value of the chart (0.0=auto scale ref to max item value)
    * @param float $barSize     Size of each bar (width for vertical chart, height for horizontal chart). 0=auto size
    * @param float $barMargin   Margin between bars (in mm, 0=no margin)
    * @param string $title                      Title of the chart  
    * @param PDFFontSettings $titleFont         Font settings for the title
    * @param array $measures                    List of measures (Each measurement is a bar set on the graph)
    * @param PDFLegendSettings $legendSettings  Legend settings
    * @param float $ticksCount                  Number of ticks on the axis with numeric values ​​(Y-axis if isVertical=true)
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                public bool $isVertical, public float $minValue, public float $maxValue, public float $barSize, public float $barMargin,
                                public string $title, public PDFFontSettings $titleFont, public array $measures, 
                                public PDFLegendSettings $legendSettings, 
                                private array $dataItems, private $ticksCount = 5) 
    {
        // Auto-create-initialize all private properties : $x1 ... $dataItems
        
        // Calcolate bar size of each bar of the chart
        //$this->calculateBarSize(0);

        // Calculate legend settings (auto size)
        if ($legendSettings->isVisible) {
            $this->legend = new PDFGraphLegend($legendSettings, null, $measures);
        }

        // Validate args
        if ($ticksCount < 2) {
            $this->ticksCount = 2;
        }
        $this->YAutoScale = ($maxValue == 0);
        $this->AutoBarSize = ($barSize == 0);
    }

    /**
     * Calculate the bar size of each bar of the chart.  
     * The index parameter is the numeric index of the series (0..N-1) to use. The bar chart currently uses only one data series, the first with index 0.
     */
    private function calculateBarSize(int $index): void {
        // Calculate the max value
        $max = 0.0;
        foreach ($this->dataItems as $item) {
            $itemValue = $item->getValue($index);
            if ($itemValue > $max) {
                $max = $itemValue;
            }
        }
        if ($this->YAutoScale) {
            // If auto scale is enabled, use the max value (auto scale)
            if ($max > $this->maxValue) {
                $this->maxValue = $max;
            } 
        }

        // Calculate the size of each chart element
        if ($this->isVertical) {
            // (1) Vertical bar chart (default/standard)
            // Calculate bar heights
            foreach ($this->dataItems as $item) {
                $itemValue = $item->getValue($index);
                $item->percentage = $itemValue / $this->maxValue;           // Calculates the percentage of the value compared to the full scale
                $height = ($this->y2 - $this->y1) * $item->percentage;      // Calculate the height of the bar
                $item->y1 = $this->y2 - $height;                            // Calculate the starting point
                if ($item->y1 < $this->y1) {
                    // It does not go out of the space assigned to the graph if the data goes out of maximum scale
                    $item->y1 = $this->y1;
                }
                $item->y2 = $this->y2;
            }
            // Calculate bar widths
            if ($this->AutoBarSize) {
                // Auto bar width (calculate based on available space)
                $totalBars = count($this->dataItems);
                $availableWidth = ($this->x2 - $this->x1) - (($totalBars - 1) * $this->barMargin);
                $barWidth = ($availableWidth / $totalBars);
                $measureBarWidth = $barWidth / count($this->measures);
                $x1 = $this->x1 + ($index * $measureBarWidth);
                foreach ($this->dataItems as $itemIndex => $item) {
                    $item->x1 = $x1;
                    $item->x2 = $x1 + $measureBarWidth;
                    // Calculate next positition
                    $x1 += $barWidth + $this->barMargin;
                }
            } else {
                // Fixed bar width
                $barWidth = $this->barSize;
                $measureBarWidth = $barWidth / count($this->measures);
                $x1 = $this->x1 + ($index * $measureBarWidth);
                foreach ($this->dataItems as $itemIndex => $item) {
                    $item->x1 = $x1;
                    $item->x2 = $x1 + $measureBarWidth;
                    // It does not go out of the space assigned to the graph if the data goes out of maximum scale
                    if ($item->x1 > $this->x2) {
                        $item->x1 = $this->x2;  
                    }
                    if ($item->x2 > $this->x2) {
                        $item->x2 = $this->x2;  
                    }
                    // Calculate next positition
                    $x1 += $barWidth + $this->barMargin;
                } 
            }
        } else {
            // (2) Horizontal bar chart
            // Calculate horizontal bar widths
            $x1 = $this->x1;
            foreach ($this->dataItems as $item) {
                $itemValue = $item->getValue($index);
                $item->percentage = $itemValue / $this->maxValue;           // Calculates the percentage of the value compared to the full scale
                $item->x1 = $this->x1;
                $width = ($this->x2 - $this->x1) * $item->percentage;       // Calcola la larghezza della barra
                $item->x2 = $this->x1 + $width;
                if ($item->x2 > $this->x2) {
                    // It does not go out of the space assigned to the graph if the data goes out of maximum scale
                    $item->x2 = $this->x2;
                }
            }
            // Calculate horizontal bar heights
            if ($this->AutoBarSize) {
                // Auto bar height (calculate based on available space)
                $totalBars = count($this->dataItems);
                $availableHeight = ($this->y2 - $this->y1) - (($totalBars - 1) * $this->barMargin);
                $barHeight = ($availableHeight / $totalBars);
                $measureBarHeight = $barHeight / count($this->measures);
                $y1 = $this->y1 + ($index * $measureBarHeight);
                foreach ($this->dataItems as $itemIndex => $item) {
                    $item->y1 = $y1;
                    $item->y2 = $y1 + $measureBarHeight;
                    // Calculate next position
                    $y1 += $barHeight + $this->barMargin;
                }                
            } else {
                // Fixed bar height
                $barHeight = $this->barSize;
                $measureBarHeight = $barHeight / count($this->measures);
                $y1 = $this->y1 + ($index * $measureBarHeight);
                foreach ($this->dataItems as $itemIndex => $item) {
                    $item->y1 = $y1;
                    $item->y2 = $y1 + $measureBarHeight;
                    // It does not go out of the space assigned to the graph if the data goes out of maximum scale
                    if ($item->y1 > $this->y2) {
                        $item->y1 = $this->y2;  
                    }
                    if ($item->y2 > $this->y2) {
                        $item->y2 = $this->y2;  
                    }
                    // Calculate next position
                    $y1 += $barHeight + $this->barMargin;
                }
            }
        }
    }
    
    public function render(?PDFReport $report = null) : void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
        
        if ($this->axisLine == null) {
            // If not set, Use default line settings for axis
            $this->axisLine = $report->GetDefaultLine();
        }

        // Background rectangle
        //$backFill = new PDFFillSettings('S', 'EEEEEE');
        //$report->PdfRectangle($this->x1, $this->y1, $this->x2, $this->y2, 0, '0000', null, $backFill);

        foreach ($this->measures as $i => $measure) {
            // Calculate bars for the current $i measure
            $this->calculateBarSize($i);
            // Data bars
            foreach ($this->dataItems as $barItem) {
                $report->PdfRectangle($barItem->x1, $barItem->y1, $barItem->x2, $barItem->y2, 0, '0000', null, $measure->fill);

                if ($this->showValuesOnDataPoint) {
                    // Show value over bar 
                    $textValue = $report->FormatValue($measure->valueFormat, $barItem->getValue($i));       // Format value
                    if ($this->isVertical) {
                        // Vertical bars - 90° oriented values
                        $barWidth = $barItem->x2 - $barItem->x1;
                        $report->pdfBox($barItem->x1, $barItem->y1 - 1, $barItem->x2 + 20, $barItem->y1 + $barWidth, $textValue, $this->axisFont, 'L', 'M', 0, null, null, 90, TextFit::Resize);
                    } else {
                        // Horizontal bars - Horizontal values
                        $report->pdfBox($barItem->x2 + 2, $barItem->y1, $barItem->x2 + 30, $barItem->y2, $textValue, $this->axisFont, 'L', 'M', 0, null, null, 0, TextFit::Resize);
                    }
                }
            }
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

        if ($this->isVertical) {
            // (1) Vertical bar chart (default/standard)
            // Draw Y axis with labels (left side)
            $axisSettings = new PDFAxisSettings($this->x1 - 15.0, $this->y1, $this->x1, $this->y2, $this->minValue, $this->maxValue, '', $axisFont, true, true, $this->axisLine);
            $axisSettings->ticksCount = $this->ticksCount;
            $axis = new PDFGraphAxis($axisSettings);            
            $axis->render($report);
            // Draw X axis with labels (bottom side)
            $axisSettings = new PDFAxisSettings($this->x1, $this->y2, $this->x2, $this->y2 + 15.0, $this->minValue, $this->maxValue, '', $axisFont, true, false, $this->axisLine, $this->dataItems, $this->barSize, $this->barMargin);
            $axis = new PDFGraphAxis($axisSettings);
            $axis->render($report);
        } else {
            // (2) Horizontal bar chart     
            // Draw Y axis with labels (left side)
            $axisSettings = new PDFAxisSettings($this->x1 - 15.0, $this->y1, $this->x1, $this->y2, $this->minValue, $this->maxValue, '', $axisFont, true, true, $this->axisLine, $this->dataItems, $this->barSize, $this->barMargin);
            $axisSettings->ticksCount = count($this->dataItems);
            $axis = new PDFGraphAxis($axisSettings);            
            $axis->render($report);
            // Draw X axis with labels (bottom side)
            $axisSettings = new PDFAxisSettings($this->x1, $this->y2, $this->x2, $this->y2 + 15.0, $this->minValue, $this->maxValue, '', $axisFont, true, false, $this->axisLine);
            $axisSettings->ticksCount = $this->ticksCount;
            $axis = new PDFGraphAxis($axisSettings);
            $axis->render($report);
        }

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

