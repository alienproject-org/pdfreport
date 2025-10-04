<?php
namespace AlienProject\PDFReport;

/**
 * KPI chart class
 * 
 * @version  	1.0.2 - 04/10/2025
 */
class PDFKpiChart {
    
    // Some private properties are automatically generated and initialized by the constructor    
    private ?PDFChartSegment $segment;          // Current segment to use for rendering


    /**
    * KPI chart class constructor
    *
    * @param float $x1                          The x-coordinate of the upper-left corner
    * @param float $y1                          The y-coordinate of the upper-left corner
    * @param float $x2                          The x-coordinate of the lower-right corner
    * @param float $y2                          The y-coordinate of the lower-right corner
    * @param string $title                      Title of the chart
    * @param PDFFontSettings $titleFont         Font settings for the title
    * @param float $radius                      Background rectangle radius (0=auto)
    * @param int $border                        Border (0:without borders, 1:with borders)
    * @param float $currentValue                Current value of the chart to display
    * @param array $segments                    Array di segmenti del gauge (PDFChartSegment)
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                private string $title, private PDFFontSettings $titleFont, 
                                private float $radius, private int $border, private float $currentValue,
                                private array $segments) 
    {
        $this->initilizeKpi();
    }

    /**
     * Initialize KPI chart (prepare for rendering)
     */
    private function initilizeKpi(): void {
        // Based on the value to be displayed, determines the segment with the display settings to use
        $this->segment = null; 
        if (!empty($this->segments) && is_array($this->segments)) {
            for ($i = 0; $i < count($this->segments); $i++) {
                $segment = $this->segments[$i];
                if (($i == 0 && $this->currentValue <= $segment->endValue) || 
                    ($i > 0 && $this->currentValue >= $segment->startValue && $this->currentValue < $segment->endValue) || 
                    ($i == (count($this->segments) -1) && $this->currentValue >= $segment->startValue)) {
                        // Segment found : Set the current segment to use for rendering
                        $this->segment = $segment;
                        break;
                }
            }
        } 
        if ($this->segment == null) {
            // No segment defined/found, use default fill color and font size
            $valueFill = new PDFFillSettings('S', '009999'); 
            $valueFont = new PDFFontSettings('helvetica', 'B', 14, '006666');
            $this->segment = new PDFChartSegment('', $this->currentValue, $this->currentValue, $valueFill, $valueFont);
        }
    }
    
    /**
     * Render the KPI chart
     */
    public function render(?PDFReport $report = null): void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
       
        // Draw the background rectangle for the KPI chart
        $borderLine = new PDFLineSettings(0.75, $report->adjustBrightnessColor($this->segment->fill->rgbColor1, -10));
        $report->PdfRectangle($this->x1, $this->y1, $this->x2, $this->y2, $this->radius, $this->border, $borderLine, $this->segment->fill);
        
        //$height = ($this->y2 - $this->y1) / 2;

        // Draw title
        $report->PdfBox($this->x1, $this->y1, $this->x2, $this->y2, $this->title, $this->titleFont, 'Center', 'Top', 0);

        // Draw value and symbol
        $valueText = number_format($this->currentValue, 1) . $this->segment->symbol;
        if (strlen($this->segment->label) > 0) {
            $verticalPos = (strlen($this->segment->label) > 0) ? 'Middle' : 'Bottom';
        }
        $report->PdfBox($this->x1, $this->y1, $this->x2, $this->y2, $valueText, $this->segment->font, 'Center', $verticalPos, 0);  

        // Draw the label (if any)
        if (strlen($this->segment->label) > 0) {
            $valueText .= "\n" . $this->segment->label;
            $report->PdfBox($this->x1, $this->y1, $this->x2, $this->y2, $this->segment->label, $this->titleFont, 'Center', 'Bottom', 0);  
        }

        /*
        // Test per disegnare un simbolo (triangolo)
        
        $points = array(75, 50,  // Vertice superiore (punta della freccia)
                50, 100, // Vertice inferiore sinistro
                100, 100); // Vertice inferiore destro

        // Disegna il poligono (il triangolo)
        // Polygon(points, style, border_style, fill_color)
        // - points: Array di coordinate [x1, y1, x2, y2, ...]
        // - style: 'F' for fill, 'D' for draw (border), 'DF' for draw and fill
        $pdf->Polygon($points, 'F');
        */

    }

}