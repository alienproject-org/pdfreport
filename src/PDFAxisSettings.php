<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing the configuration of a chart axis with labels
 * 
 * File :       PDFAxisSettings.php
 * @version  	1.0.3 - 08/10/2025
 */
class PDFAxisSettings 
{    
    // Settings
    public float $tickSize = 1.2;                       // tick size : mm
    public float $ticksCount = 5;                       // total number of ticks
    public float $labelHeight = 6.0;
    public float $labelWidth = 10.0;
    public float $titleHeight = 6.0;
    public bool $isLabelVisible = true;
    
    /**
     * Class constructor
     * 
     * x1..y2 : area for axis rendering
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
                                public ?PDFLineSettings $line = null) {
        // Automatically creates and initializes all properties specified as public or private as constructor arguments : $x1 ... $line
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