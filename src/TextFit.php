<?php

namespace AlienProject\PDFReport;

/**
 * TextFit enum used by PDFBoxSettings class
 *
 * File :       TextFit.php
 * @version  	1.0.10 - 13/08/2026
 */

enum TextFit : int
{
    case None = 0;
    case Auto = 1;          // Truncate if possible, otherwise resize (default)
    case Truncate = 2;
    case Resize = 3;
}
