<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing the configuration of a chart legend
 * 
 * File :       PDFLegendSettings.php
 * @version  	1.0.12 - 05/10/2026
 */
class PDFLegendSettings 
{    
    // Padding and margins (attributes of the <legend> element : padding, itemmargin, boxsize, titleheight, labelheight)
    public float $padding = 2;                       // padding size : mm
    public float $marginBetweenItems = 1;            // vertical space between the items (vertical legend)
    public float $boxSize = 5;                       // size of the color box (swatch) of each item
    public float $titleHeight = 6;
    public float $itemLabelHeight = 6;               // height of each item row (the color box is vertically centered)
    public bool $isValueVisible = true;              // pie / single bar charts : print the value after the label (showvalues)
    public string $valueFormat = '';                 // format mask of the values (empty = default format, see PDFReport::FormatChartValue)
    
    /**
     * Costruttore della classe
     * 
     * x1..y2 : area sfondo della legenda, $radius raggio per angoli arrotondati 
     */
    public function __construct(public float $x1, public float $y1, public float $x2, public float $y2, 
                                public float $radius = 0,           // Background rectangle corner radius
                                public bool $isVisible = true,
                                public float $opacity = 1.0,        // 0..1 (eg. 0.5 = 50%)
                                // Title settings 
                                public string $title = '', 
                                public ?PDFFontSettings $font = null,
                                // Legend settings
								public bool $isVertical = true,
                                public ?PDFLineSettings $line = null,
                                public ?PDFFillSettings $fill = null) {
        // Automatically creates and initializes all properties specified as public or private as constructor arguments : $x1 ... $fill
    }
    
    public function setSize(float $x1, float  $y1, float  $x2, float  $y2) 
    {
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
    }

}