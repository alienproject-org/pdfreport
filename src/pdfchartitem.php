<?php
namespace AlienProject\PDFReport;

/**
 * Class that defines a single element (value) to be displayed in a graph
 * 
 * @version  	1.0.1 - 16/09/2025
 */
class PDFChartItem {
    
    public function __construct(public string $label = '', 
								public float $value = 0.0,              // value
								public float $percentage = 0.0,         // value % (ref to a total value) 
								public ?PDFFillSettings $fill = null, 
								public float $x1 = 0.0, 
								public float $y1 = 0.0,
                                public float $x2 = 0.0, 
								public float $y2 = 0.0, 
								public float $radius = 0.0, 
								public float $startAngle = 0.0, 
								public float $endAngle = 360.0) {
        // Auto-create-initialize all public properties : $label ... $endAngle
    }
}

