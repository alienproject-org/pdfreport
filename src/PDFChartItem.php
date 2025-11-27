<?php

namespace AlienProject\PDFReport;

/**
 * Class that defines a single element (value) to be displayed in a graph
 * 
 * File :       PDFChartItem.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFChartItem {
    
    public function __construct(public string $label = '', 
								private array  $values = [ 0.0 ],       // values
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

    /**
     * Gets the value based on the index. If no index is specified, it returns the value of the first element. If the index is invalid, it returns 0.
     */
    public function getValue(int $index = 0) {
        if ($index >= 0 && $index < count($this->values)) {
            return $this->values[$index];
        } 
        return 0.0;
    }
}

