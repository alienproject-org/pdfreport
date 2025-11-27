<?php

namespace AlienProject\PDFReport;

enum TextFit : int
{
    case None = 0;
    case Auto = 1;          // Truncate if possible, otherwise resize (default)
    case Truncate = 2;
    case Resize = 3;
}

/**
 * PDFBoxSettings class
 *
 * File :       PDFBoxSettings.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFBoxSettings
{
    // PDF box (draw area) settings
    public float $x1 = 0.0;
    public float $y1 = 0.0;
    public float $x2 = 0.0;
    public float $y2 = 0.0;
    public float $width = 0.0;
    public float $height = 0.0;
    public TextFit $textFit = TextFit::Auto;
    
    function __construct(float $x1, float $y1, float $x2, float $y2, TextFit $textFit)
    {
        $this->Initialize($x1, $y1, $x2, $y2, 0, 0, $textFit);
    }

    public function Initialize(float $x1, float $y1, float $x2, float $y2, float $width, float $height, TextFit $textFit)
    {
        $this->x1 = $x1;
        $this->y1 = $y1;
        if ($width == 0 && $height == 0) {
            // Use x2,y2 and calculate width,height
            if ($x2 < $x1) {
                $x2 = $x1;
            }
            if ($y2 < $y1) {
                $y2 = $y1;
            }
            $this->x2 = $x2;
            $this->y2 = $y2;
            $this->width = $x2 - $x1;
            $this->height = $y2 - $y1;
        } else {
            // Use width,height and calculate x2,y2 
            if ($width < 0) {
                $width = 0;
            }
            if ($height < 0) {
                $height = 0;
            }
            $this->x2 = $x1 + $width;
            $this->y2 = $y1 + $height;
            $this->width = $width;
            $this->height = $height;
        }
    }

}

