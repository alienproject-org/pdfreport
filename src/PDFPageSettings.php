<?php

namespace AlienProject\PDFReport;

/**
 * PDFPageSettings class
 *
 * File :       PDFPageSettings.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFPageSettings
{
    // PDF page settings
    public string $format = 'A4';                   // Page size. Use standard page coding (A3,A4,A5,Letter,...) or "C" for custom page size (set also width and height)
    public string $orientation = 'P';               // P-portrait, L-landscape
    public string $unit = 'mm'; 
    public int $width = 210;
    public int $height = 297;
    
    function __construct($format = 'A4', $orientation = 'P', $unit = 'mm', $width = 210, $height = 297)
    {
        $this->Initialize($format, $orientation, $unit, $width, $height);
    }

    public function Initialize($format = 'A4', $orientation = 'P', $unit = 'mm', $width = 210, $height = 297)
    {
        // Format
        $format = trim($format);
        switch (strtolower($format)) {
            case 'c':
            case 'cust':
            case 'custom':
                // Custom format
                $format = 'C';
                break;
        }
        $this->format = $format;
        // Orientation
        $orientation = trim($orientation);
        switch (strtolower($orientation)) {
            case 'l':
            case 'land':
            case 'landscape':
            case 'h':
            case 'horiz':
            case 'horizontal':
                // Normalize orientation : L - Landscape
                $orientation = 'L';
                break;
            case 'p':
            case 'port':
            case 'portrait':
            case 'v':
            case 'vert':
            case 'vertical':
            default:
                // Normalize orientation : P - Portrait  (default)
                $orientation = 'P';
                break;
        }
        $this->orientation = $orientation;
        // Unit
        $unit = trim($unit);
        switch (strtolower($unit)) {
            case 'mm':
            case 'in':
            case 'pt':
            case 'cm':
                // Valid unit
                $unit = strtolower($unit); 
                break;
            default:
                // Innvalid unit (use default : mm)
                $unit = 'mm';
                break;
        }
        $this->unit = $unit;
        // Custom size
        if ($width < 10) $width = 10;
        if ($height < 10) $height = 10;
        $this->width = $width;
        $this->height = $height;
    }

}

