<?php

namespace AlienProject\PDFReport;

/**
 * PDFMeasure class
 *
 * File :       PDFMeasure.php
 * @version  	1.0.4 - 15/11/2025
 */
class PDFMeasure
{
    // PDF public properties auto created into class constructor
    

    function __construct(public string $label, public PDFLineSettings $line, public ?PDFSymbolSettings $symbol)
    {
    }

}

