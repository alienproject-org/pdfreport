<?php 

/**
 * Gauge chart
 * 
 * @version  	1.0.1 - 16/09/2025
 */
class GaugeChart
{
	public $value = 0.0;
	public $minValue = 0.0;
	public $maxValue = 100.0;
	
	public $x1 = 0.0;		// mm - Chart container
	public $y1 = 0.0;		// mm
	public $x2 = 40.0;		// mm
	public $y2 = 20.0;		// mm
	
	public $orientation = 'V';      // V - vertical
	
	function __construct() {
        //parent::__construct();
    }
	
	public function render()
	{
		
	}
	
	private function calculateChartArea()
	{
	}
	
}

?>