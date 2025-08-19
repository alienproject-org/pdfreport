<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * PDFPageSettings class
 *
 */
class PDFPageSettings
{
    // PDF page settings
    public string $format = 'A4';                   // Page size
    public string $orientation = 'P';               // P-portrait, L-landscape
    public string $unit = 'mm'; 
    
    function __construct($format = 'A4', $orientation = 'P', $unit = 'mm')
    {
        $this->Initialize($format, $orientation, $unit);
    }

    public function Initialize($format = 'A4', $orientation = 'P', $unit = 'mm')
    {
        $this->format = $format;
        $this->orientation = $orientation;
        $this->unit = $unit;
    }

}

