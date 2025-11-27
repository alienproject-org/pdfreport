<?php

namespace AlienProject\PDFReport;

/**
 * PDFMeasure class
 *
 * File :       PDFMeasure.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFMeasure
{
    // PDF public properties auto created into class constructor
    
    /**
     * valueFormat      Value format mask or value format key for user defined callback format function
     * Format mask eg. > "":use value as it is, "F2":Float 2 decimals, "C3 €":currency 3 decimals with " €" suffix, "P1": % 1 decimal with % suffix, "I":integer, "N":none (empty) 
     */
    function __construct(public string $id, public string $label, public string $valueFormat, public PDFLineSettings $line, public PDFFillSettings $fill, public ?PDFSymbolSettings $symbol)
    {
    }

}

