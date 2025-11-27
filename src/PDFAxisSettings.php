<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing the configuration of a chart axis with labels
 * 
 * File :       PDFAxisSettings.php
 * @version  	1.0.5 - 27/11/2025
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