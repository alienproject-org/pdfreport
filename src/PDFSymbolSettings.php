<?php

namespace AlienProject\PDFReport;

/**
 * PDFSymbolSettings class
 *
 * File :       PDFSymbolSettings.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFSymbolSettings
{
    // PDF symbol settings public properties
    public string $shape = 'N';                     // Symbol shape: N-none (default), C-circle, S-square, ...
    public float $size = 2.0;						// Symbol size (mm), for the circle is its diameter
    public ?PDFFillSettings $fill = null;           // Fill settings for the symbol (null=no fill)
    public ?PDFLineSettings $line = null;           // Border line settings for the symbol (null=no border)
    
    function __construct($shape= 'N', $size = 2.0, $symbolLine = null, $symbolFill = null)
    {
        $this->Initialize($shape, $size, $symbolLine, $symbolFill);
    }

    public function Initialize($shape= 'N', $size = 2.0, $symbolLine = null, $symbolFill = null)
    {
        $shape = strtoupper(trim($shape));
        $shape = $shape[0];
        if (!in_array($shape, ['N', 'C', 'S'])) {
            // In case of invalid shape code, set default shape to NONE
            $shape = 'N';
        }
        $this->shape = $shape;
        if ($size < 0) {
            $size = 0;
        }
        if ($size > 10) {
            $size = 10;
        }
        $this->size = $size;
        $this->line = $symbolLine;
        $this->fill = $symbolFill;
    }

}

