<?php
namespace AlienProject\PDFReport;

/**
 * Class to generate a single bar chart
 * 
 * @version  	1.0.1 - 16/09/2025
 */
class PDFSingleBarChart {
    /**
     * Private properties automatically generated and initialized by the constructor
     * $x1, $y1, $x2, $y2 
     * isVertical           bool, true=vertical, false=horizontal
     * minValue             float, minimum value of the chart
     * maxValue             float, maximum value of the chart (0.0=auto scale to total items value)
     * legendSettings       PDFGraphLegendSettings object
     * $dataItems           Array of PDFChartItem object
     */
    
    private float $total = 0.0;                 // Total value of all data items
    // Legend settings
    public ?PDFGraphLegend $legend = null;      // null=no legend

    /**
    * PieChart class constructor
    *
    * @param float $x1          The x-coordinate of the upper-left corner
    * @param float $y1          The y-coordinate of the upper-left corner
    * @param float $x2          The x-coordinate of the lower-right corner
    * @param float $y2          The y-coordinate of the lower-right corner
    * @param array $dataItems   The PDFChartItem array with label, value, color and other properties for each sector
    * @param string             $style  The pie chart style ('CLASSIC', 'DEFAULT', 'RING', 'DONUTS')
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                public bool $isVertical, public float $minValue, public float $maxValue,
                                public string $title, public PDFFontSettings $titleFont, 
                                public PDFLegendSettings $legendSettings, 
                                private array $dataItems) 
    {
        // Auto-create-initialize all private properties : $x1 ... $dataItems
        
        // Calcolate bar size of each element of the chart
        $this->calculateBarSize();

        // Calculate legend settings (auto size)
        if ($legendSettings->isVisible) {
            $this->legend = new PDFGraphLegend($legendSettings, $this->dataItems);
        }
    }

    /**
     * Calculate the bar size of each element of the chart (values are stacked)
     */
    private function calculateBarSize(): void {
        // Calculate the total sum of the values
        $total = 0.0;
        foreach ($this->dataItems as $item) {
            $total += $item->value;
        }
        $this->total = $total;
        if ($this->maxValue == 0.0) {
            // If maxValue is not set, use the total value (auto scale)
            $this->maxValue = $total;
        }

        // Calcola la dimensione di ogni elemento del grafico
        if ($this->isVertical) {
            // Vertical bar chart
            $y2 = $this->y2;
            foreach ($this->dataItems as $item) {
                $item->percentage = $item->value / $this->maxValue;         // Calcola la percentuale del valore rispetto al fondo scala
                $item->x1 = $this->x1; 
                $height = ($this->y2 - $this->y1) * $item->percentage;      // Calcola l'altezza della barra
                $item->y1 = $y2 - $height;                                  // Calcola il punto di partenza
                if ($item->y1 < $this->y1) {
                    // Non esce dallo spazio assegnato al grafico se i dati vanno fuori scala massima
                    $item->y1 = $this->y1;
                    $height = 0;
                }
                $item->x2 = $this->x2;
                $item->y2 = $y2;
                // Values are stacked, so next bar starts from the end of the previous one
                $y2 -= $height;
            }
        } else {
            // Horizontal bar chart
            $x1 = $this->x1;
            foreach ($this->dataItems as $item) {
                $item->percentage = $item->value / $this->maxValue;         // Calcola la percentuale del valore rispetto al fondo scala
                $item->x1 = $x1;
                $item->y1 = $this->y1; 
                $width = ($this->x2 - $this->x1) * $item->percentage;       // Calcola la larghezza della barra
                $item->x2 = $x1 + $width;
                $item->y2 = $this->y2;
                // Values are stacked, so next bar starts from the end of the previous one
                $x1 += $width;                
            }
        }
    }
    
    public function render(?PDFReport $report = null) : void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
        
        // Background rectangle
        $backFill = new PDFFillSettings('S', 'EEEEEE');
        $report->PdfRectangle($this->x1, $this->y1, $this->x2, $this->y2, 0, '0000', null, $backFill);
        // Data bars
        foreach ($this->dataItems as $barItem) {
            $report->PdfRectangle($barItem->x1, $barItem->y1, $barItem->x2, $barItem->y2, 0, '0000', null, $barItem->fill);
        }
        
        // Title
        if ($this->title != '') {
            $cellHeightRatio = $pdf->getCellHeightRatio();
            $singleLineHeight = $this->titleFont->size * $cellHeightRatio;
            if ($this->isVertical) {
                // Vertical bar chart
                
            } else {
                // Horizontal bar chart
                $report->pdfBox($this->x1, $this->y1 - $singleLineHeight, $this->x2, $this->y1, $this->title, $this->titleFont, 'C', 'M', 0);
            }
        }

        // Draw axis with labels
        $axisFont = new PDFFontSettings('helvetica', '', 8, '000000');
        $axisLine = new PDFLineSettings(0.2, '000000');
        if ($this->isVertical) {
            // Axis for vertical bar chart (left side)
            $axisSettings = new PDFAxisSettings($this->x1 - 15.0, $this->y1, $this->x1, $this->y2, $this->minValue, $this->maxValue, '', $axisFont, true, true, $axisLine);
        } else {
            // Axis for horizontal bar chart (bottom side)
            $axisSettings = new PDFAxisSettings($this->x1, $this->y2, $this->x2, $this->y2 + 15.0, $this->minValue, $this->maxValue, '', $axisFont, true, false, $axisLine);    
        }
        $axis = new PDFGraphAxis($axisSettings);
        $axis->render($report);

        /*
        // Total label
        if ($this->isVertical) {
            // Vertical bar chart 
            $x = $this->x1 + (($this->x2 - $this->x1) / 2);
            $y = $this->y1 + (($this->y2 - $this->y1) / 2);
            $pdf->MultiCell($this->x2 - $this->x1, $this->y2 - $this->y1, "TOTAL " . $this->total, 0, 'C', false, 1, $x, $y, false, 0, false, true, 0, 'M', true);
        } else {
            // Horizontal bar chart
            $x = $this->x1 + (($this->x2 - $this->x1) / 2);
            $y = $this->y1 + (($this->y2 - $this->y1) / 2);
            $pdf->MultiCell($this->x2 - $this->x1, $this->y2 - $this->y1, "TOTAL " . $this->total, 0, 'C', false, 1, $x, $y, false, 0, false, true, 0, 'M', true);
        }
        */

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

