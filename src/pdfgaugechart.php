<?php
namespace AlienProject\PDFReport;

/**
 * Gauge chart class
 * 
 * @version  	1.0.1 - 16/09/2025
 */
class PDFGaugeChart {
    
    // Some private properties are automatically generated and initialized by the constructor
    
    private float $xc;
    private float $yc;
    private float $calculatedAngle;
    private float $percentage;
    private ?PDFChartSegment $segment;          // Current segment to use for rendering

    // Semicircular gauge angles
    private const START_ANGLE = -90;            // Starting angle (left)
    private const END_ANGLE = 90;               // Final corner (right)
    private const TOTAL_ANGLE = 180;            // Total gauge angle

    /**
    * Gauge chart class constructor
    *
    * @param float $x1                          The x-coordinate of the upper-left corner
    * @param float $y1                          The y-coordinate of the upper-left corner
    * @param float $x2                          The x-coordinate of the lower-right corner
    * @param float $y2                          The y-coordinate of the lower-right corner
    * @param string $title                      Title of the chart
    * @param PDFFontSettings $titleFont         Font settings for the title
    * @param float $radius                      Gauge chart radius (0=auto)
    * @param int $border                        Border (0:without borders, 1:with borders)
    * @param string $style                      Gauge style ('CLASSIC', 'DEFAULT', 'RING', 'GAUGE')
    * @param float $minValue                    Minimum value of the gauge (corresponds to -90° start angle)
    * @param float $maxValue                    Maximum value of the gauge (corresponds to +90° end angle)
    * @param float $currentValue                Current value of the chart to display
    * @param array $segments                    Gauge segment array (PDFChartSegment)
    * @param PDFFillSettings $backgroundFill    Ring background color (default light gray if not specified)
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                private string $title, private PDFFontSettings $titleFont, 
                                private float $radius, private int $border, private string $style, 
                                private float $minValue, private float $maxValue, private float $currentValue,
                                private array $segments,
                                private $backgroundFill = null) 
    {
        // Parameter validation
        if ($this->maxValue <= $this->minValue) {
            throw new \Exception('Max value must be greater than min value.');
        }
        
        // Calculate the center position and radius
        $max_radius = min(($this->x2 - $this->x1) / 2.0, ($this->y2 - $this->y1) / 2.0);  
        if ($this->radius <= 0 || $this->radius > $max_radius) {
            // Auto calculate radius or reduce it to fill chart into container
            $this->radius = $max_radius;
        } 

        // Calculate center position
        $this->xc = $this->x1 + (($this->x2 - $this->x1) / 2);
        $this->yc = $this->y1 + (($this->y2 - $this->y1) / 2);

        // Calculate gauge settings (prepare for rendering)
        $this->calculateGaugeValues();
    }

    /**
     * Calculate the gauge values and angles
     */
    private function calculateGaugeValues(): void {
        // Limit the current value to the min-max range
        $clampedValue = max($this->minValue, min($this->currentValue, $this->maxValue));
        
        // Calculate the percentage of the range
        $valueRange = $this->maxValue - $this->minValue;
        $this->percentage = ($clampedValue - $this->minValue) / $valueRange;
        
        // Calculate final angle based on percentage
        $this->calculatedAngle = self::START_ANGLE + (self::TOTAL_ANGLE * $this->percentage);

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
     * Render the gauge chart
     */
    public function render(?PDFReport $report = null): void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
        
        $sectorStyle = 'F';
        if ($this->border)
            $sectorStyle .= 'D';

        // Draw title
        $report->PdfBox($this->x1, $this->y1 - 8, $this->x2, $this->y1, $this->title, $this->titleFont, 'Center', 'Top', 0);

        // Draw background gauge (full semicircle)
        if ($this->backgroundFill != null) {
            $rgb = $this->backgroundFill->GetStartColor();
            $pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]); 
        } else {
            // Default background color (light gray)
            $pdf->setFillColor(220, 220, 220);
        }
        
        // Draw the complete background ring
        $pdf->PieSector($this->xc, $this->yc, $this->radius, self::START_ANGLE, self::END_ANGLE, $sectorStyle);

        // Draw value gauge (partial fill based on current value)
        if ($this->percentage > 0) {
            $rgb = $this->segment->fill->GetStartColor();
            $pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]); 
            // Disegna solo la parte riempita
            $pdf->PieSector($this->xc, $this->yc, $this->radius, self::START_ANGLE, $this->calculatedAngle, $sectorStyle);
        }

        // Apply gauge style (create inner hole for ring/gauge effect)
        switch (strtolower($this->style)) {
            case 'ring':
            case 'gauge':
            case 'donuts':
                // Draw a white circle inside to create the ring effect
                $pdf->setFillColor(255, 255, 255);
                $innerRadius = $this->radius * 0.6; // Hole size (60% of outer radius)
                $pdf->Circle($this->xc, $this->yc, $innerRadius, 0, 360, 'F');
                break;
            default:
                // Default style does not create an inner hole
                break;
        }

        // Draw gauge labels and indicators
        $this->drawGaugeLabels($pdf, $report);
    }

    /**
     * Draw gauge labels and value indicators
     */
    private function drawGaugeLabels($pdf, $report): void {
        // Set text color to black
        $pdf->setTextColor(0, 0, 0);
        
        // Current value label (center of gauge)
        $valueText = number_format($this->currentValue, 1) . $this->segment->symbol;
        $percentageText = number_format($this->percentage * 100, 1) . ' %';
        
        // Position for center text
        $centerY = $this->yc + ($this->radius * 0.2);
        
        // Value text
        if (strlen($this->segment->label) > 0) {
            $valueText .= "\n" . $this->segment->label;
        }
        $report->PdfBox($this->x1, $this->y1, $this->x2, $this->yc, $valueText, $this->segment->font, 'C', 'B', 0);  

        // Percentage text
        /*
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Text($this->xc - 8, $centerY + 8, $percentageText);
        */

        $font = new PDFFontSettings('helvetica', '', 8, '000000');
        $report->PdfBox($this->xc - $this->radius, $this->yc + 1, 
                        $this->xc, $this->yc + 6, number_format($this->minValue, 0), $font, 'L', 'C', 0);  
        $report->PdfBox($this->xc, $this->yc + 1, 
                        $this->xc + $this->radius, $this->yc + 6, number_format($this->maxValue, 0), $font, 'R', 'C', 0);
    }

    /**
     * Get current gauge information
     *
     * @return array
     */
    public function getGaugeInfo(): array {
        return [
            'minValue' => $this->minValue,
            'maxValue' => $this->maxValue,
            'currentValue' => $this->currentValue,
            'percentage' => $this->percentage,
            'angle' => $this->calculatedAngle,
            'center' => ['x' => $this->xc, 'y' => $this->yc],
            'radius' => $this->radius
        ];
    }

    /**
     * Set a new current value and recalculate
     */
    public function setCurrentValue(float $newValue): void {
        $this->currentValue = $newValue;
        $this->calculateGaugeValues();
    }

    /**
     * Get the current percentage
     */
    public function getPercentage(): float {
        return $this->percentage;
    }

    /**
     * Check if the current value exceeds the maximum
     */
    public function isOverLimit(): bool {
        return ($this->currentValue > $this->maxValue);
    }
}