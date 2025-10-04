<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * Class for managing the configuration of a chart legend
 * 
 * @version  	1.0.2 - 04/10/2025
 */
class PDFLegendSettings 
{    
    // Padding e margini
    public float $padding = 2;                       // padding size : mm
    public float $marginBetweenItems = 1;
    public float $boxSize = 5;
    public float $titleHeight = 6;
    public float $itemLabelHeight = 6;
    public bool $isValueVisible = true;
    
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