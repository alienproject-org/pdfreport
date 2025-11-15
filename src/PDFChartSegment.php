<?php

namespace AlienProject\PDFReport;

/**
 * Class that defines the settings to be used in a graph if the value to be displayed falls within the specified range (minimum and maximum)
 * 
 * File :       PDFChartSegment.php
 * @version  	1.0.4 - 15/11/2025
 */
class PDFChartSegment {
    
    public function __construct(public string $label = '', 
								public float $startValue = 0.0,             // segment start (min) value
                                public float $endValue = 0.0,               // segment end (max) value
								public ?PDFFillSettings $fill = null, 
                                public ?PDFFontSettings $font = null,
								public string $symbol = '') {
        // Auto-create-initialize all public properties : $label ... $symbol
    }
}

