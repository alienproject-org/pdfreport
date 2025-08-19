<?php
namespace AlienProject\PDFReport;

/**
 * Classe per generare un grafico a torta
 */
class PDFPieChart {
    /**
     * Private properties automatically generated and initialized by the constructor
     * $x1, $y1, $x2, $y2 
     * $border              0, 1
     * $style               ''/'CLASSIC'/'DEFAULT'  or  'RING'/'DONUTS'
     * legendSettings       PDFGraphLegendSettings object
     * $dataItems           Array of PDFChartItem object
     */
    
    private float $xc;
    private float $yc;
    private float $total = 0.0;
    // Legend settings
    private bool $legendIsVisible = false;
    public ?PDFGraphLegend $legend = null;

    /**
    * PieChart class constructor
    *
    * @param float $x1          The x-coordinate of the upper-left corner
    * @param float $y1          The y-coordinate of the upper-left corner
    * @param float $x2          The x-coordinate of the lower-right corner
    * @param float $y2          The y-coordinate of the lower-right corner
    * @param array $dataItems   The PDFChartItem array with label, value, color and other properties for each sector
    * @param string             $style  The pie chart style ('CLASSIC', 'DEFAULT', 'RING', 'DONUTS')
    */
    public function __construct(private float $x1, private float $y1, private float $x2, private float $y2, 
                                private float $radius, private $border, private string $style, public PDFLegendSettings $legendSettings, 
                                private array $dataItems) 
    {
        // Auto-create-initialize all private properties : $x1 ... $data
        
        // Calculate the center postion and radius
        $max_radius = min(($this->x2 - $this->x1) / 2.0, ($this->y2 - $this->y1) / 2.0);  
        if ($this->radius <= 0 || $this->radius > $max_radius) {
            // Auto calculate radius or reduce it to fill chart into container
            $this->radius = $max_radius;
        } 
        // Calculate legend settings (centered)
        $this->xc = $this->x1 + (($this->x2 - $this->x1) / 2);
        $this->yc = $this->y1 + (($this->y2 - $this->y1) / 2);

        // Calcolate sectors
        $this->calculateSectors();

        // Calculate legend settings (auto size)
        if ($legendSettings->isVisible) {
            $this->legend = new PDFGraphLegend($legendSettings, $this->dataItems);
        }
    }

    /**
     * Calculate the sectors of the pie chart
     */
    private function calculateSectors(): void {
        // Calcola la somma totale dei valori
        $total = 0.0;
        foreach ($this->dataItems as $item) {
            $total += $item->value;
        }
        
        // Verifica se il totale è maggiore di zero
        if ($total <= 0) {
            throw new \Exception('Total is 0 - Invalid data source.');
        }
        $this->total = $total;

        // Calcola l'angolo iniziale e finale per ogni settore
        $startAngle = 0;
        foreach ($this->dataItems as $item) {
            // Calcola la percentuale e l'angolo del settore
            $item->x1 = $this->xc;
            $item->y1 = $this->yc; 
            $item->radius = $this->radius;
            $item->percentage = $item->value / $total;
            $angle = 360 * $item->percentage;
            $endAngle = $startAngle + $angle;
            $item->startAngle =  $startAngle;
            $item->endAngle = $endAngle;    
            // Aggiorna l'angolo di inizio per il prossimo settore
            $startAngle = $endAngle;
        }
    }
    
    public function render(?PDFReport $report = null) : void 
    {
        if ($report == null) return;
        $pdf = $report->pdf;
        if ($pdf == null) return;
        $pieSectorStyle = 'F';
        if ($this->border)
            $pieSectorStyle .= 'D';
        foreach ($this->dataItems as $sector) {
            if ($sector->fill != null) {
                $rgb = $sector->fill->GetStartColor();
                $pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]); 
            }
            $pdf->PieSector($sector->x1, $sector->y1, $sector->radius, $sector->startAngle, $sector->endAngle, $pieSectorStyle);
        }
        // Apply piechart style (if required)
        switch (strtolower($this->style)) {
            case 'ring':
            case 'donuts':
                // Draw a white circle inside 
                $pdf->setFillColor(255, 255, 255);
                $pdf->Circle($this->xc, $this->yc, $this->radius / 1.5, 0, 360, $pieSectorStyle);
                break;
        }
        // Total label
        $x = $this->xc - $this->radius;
        $y = $this->yc - $this->radius;
        $pdf->MultiCell($this->radius * 2, $this->radius * 2, "TOTAL " . $this->total, 0, 'C', false, 1, $x, $y, false, 0, false, true, 0, 'M', true);
        // Print legend
        if ($this->legend != null)
            $this->legend->render($report);
        /*
        // Restore default fill color
        if ($defaultFill != null) {
            $rgb = $defaultFill->GetStartColor();
            $pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]);
        }
        */
    }

    /**
     * Restituisce i settori calcolati
     *
     * @return array
     */
    public function getSectors(): array {
        return $this->dataItems;
    }  
}

