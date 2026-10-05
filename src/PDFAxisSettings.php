<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing the configuration of a chart axis with labels
 * 
 * File :       PDFAxisSettings.php
 * @version  	1.0.12 - 05/10/2026
 */
class PDFAxisSettings 
{    
    // Settings
    public float $tickSize = 1.2;                       // tick size : mm
    public float $ticksCount = 5;                       // total number of ticks
    public float $labelHeight = 6.0;                    // mm
    public float $labelWidth = 10.0;                    // mm
    public float $titleHeight = 6.0;                    // mm
    public bool $isLabelVisible = true;
    public string $valueFormat = '';                    // Format mask of the numeric labels (eg. "F1", "I", "C0 $"), empty = default format (see PDFReport::FormatChartValue)
    
    /**
     * Class constructor
     * 
     * x1..y2 : area for axis rendering
     * $dataItems           Array of PDFChartItem object. If defined, draw a tick for each element and use its label.
     * tickDistance        float, distance between ticks (0=auto calculate)
     * tickMargin          float, extra margin to add to tickDistance (0=no extra margin)
     */
    public function __construct(public float $x1, public float $y1, public float $x2, public float $y2, 
                                public float $minValue = 0.0, 
                                public float $maxValue = 100.0,
                                // Title settings 
                                public string $title = '', 
                                public ?PDFFontSettings $font = null,
                                // Other settings
                                public bool $isVisible = true,
								public bool $isVertical = true,
                                public ?PDFLineSettings $line = null,
                                public array $dataItems = [], public float $tickDistance = 0, public $tickMargin = 0) {
        // Automatically creates and initializes all properties specified as public or private as constructor arguments : $x1 ... $line
        if (count($dataItems) > 0) {
            $this->ticksCount = count($dataItems);
            // Calculate label width based on number of data items and available space
            if (!$this->isVertical) {
                $availableWidth = $this->x2 - $this->x1;
                $this->labelWidth = $availableWidth / count($dataItems);
                if ($this->labelWidth > 60.0) {
                    $this->labelWidth = 60.0;    // Max label width
                }
                if ($this->labelWidth < 10.0) {
                    $this->labelWidth = 10.0;     // Min label width
                }
            } 
        }   
    }
    
    /**
     * Returns the full scale value of an auto scale axis: the max value rounded up so that the step between the ticks 
     * is a "round" number (1, 2, 2.5 or 5 x 10^n), eg. max 265 with 5 ticks > 300 (labels 0, 75, 150, 225, 300).
     *
     * @param float $minValue       Minimum value of the axis
     * @param float $maxValue       Max data value
     * @param int $ticksCount       Number of ticks (labels) of the axis
     * @return float                Full scale value
     */
    public static function NiceMaxValue(float $minValue, float $maxValue, int $ticksCount): float
    {
        $intervals = max(1, $ticksCount - 1);
        $range = $maxValue - $minValue;
        if ($range <= 0) {
            return $minValue + $intervals;      // No data (or all the values equal to the min value): step 1
        }
        $rawStep = $range / $intervals;
        $magnitude = pow(10, floor(log10($rawStep)));
        $step = 10 * $magnitude;
        foreach ([ 1, 2, 2.5, 5, 10 ] as $multiplier) {
            if ($multiplier * $magnitude >= $rawStep - 1e-12) {
                $step = $multiplier * $magnitude;
                break;
            }
        }
        return $minValue + ($step * $intervals);
    }

    /*
    public function setSize(float $x1, float  $y1, float  $x2, float  $y2) 
    {
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
    }
    */

}