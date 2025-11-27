<?php

namespace AlienProject\PDFReport;

/**
 * PDFFillSettings class
 *
 * File :       PDFFillSettings.php
 * @version  	1.0.5 - 27/11/2025
 */
class PDFFillSettings
{
    // PDF page settings
    public string $type = 'N';                      // N-None, S-solid, L/G-linear gradient, R-radial gradient
    public string $rgbColor1 = 'FFFFFF';            // Color RGB hex format (FFFFFF : white)
    public string $rgbColor2 = 'FFFFFF';            
    private array $color1 = [0, 0, 0];              // RGB array color [ R, G, B ], RGB : 0..255
    private array $color2 = [0, 0, 0];
    private bool $isVertical = false;               // Direction for linear gradient. isVertical = false > Horizontal (default), isVertical = true > Vertical

    function __construct($type = 'N', $rgbColor1 = 'FFFFFF', $rgbColor2 = 'FFFFFF', bool $isVerticalDirection = false)
    {
        $this->Initialize($type, $rgbColor1, $rgbColor2, $isVerticalDirection);
    }

    public function Initialize($type = 'N', $rgbColor1 = 'FFFFFF', $rgbColor2 = 'FFFFFF', bool $isVerticalDirection = false)
    {
        // Validate and fix type
        $type = substr(strtoupper(trim($type)), 0, 1);
        if (!in_array($type, ['N', 'S', 'L', 'G', 'R'])) {
            // In case of invalid fill type code, set default type to NONE
            $type = 'N';
        }
        $this->type = $type;
        $this->rgbColor1 = $rgbColor1;
        $this->color1 = $this->hexToRgbArray($rgbColor1);
        $this->rgbColor2 = $rgbColor2;
        $this->color2 = $this->hexToRgbArray($rgbColor2);
        $this->isVertical = $isVerticalDirection;
    }

    /**
     * Gets the initial color as an array with the 3 decimal values ​​[R, G, B]
     */
    public function GetStartColor()
    {
        $this->color1 = $this->hexToRgbArray($this->rgbColor1);
        return $this->color1;
    }

    /**
     * Gets the final color as an array with the 3 decimal values ​​[R, G, B]
     */
    public function GetEndColor()
    {
        $this->color2 = $this->hexToRgbArray($this->rgbColor2);
        return $this->color2;
    }

    public function GetDirectionArray(float $x1, float $y1, float $x2, float $y2) : array
    {
        $coord = [];
        if ($this->isVertical) {
            // Vertical direction
            $coord = [ 0, 1, 0, 0 ];
        } else {
            // Horizontal direction
            $coord = [ 0, 0, 1, 0 ];
        }
        return $coord;
    }

    /**
     * Returns an array with the 3 decimal values [R, G, B]
     */
    private function hexToRgbArray(string $hexColor): array 
    {
        // Removes the # character at the beginning of the text, if present
        $hexColor = ltrim($hexColor, '#');
        
        // Make sure the string is 6 characters long
        if (strlen($hexColor) !== 6) {
            throw new \InvalidArgumentException('PDFReport.hexToRgbArray : The color must be expressed in hexadecimal format, 6 characters long (' . $hexColor . ')');
        }
        
        // Extracts the red, green and blue components
        $red = hexdec(substr($hexColor, 0, 2));
        $green = hexdec(substr($hexColor, 2, 2));
        $blue = hexdec(substr($hexColor, 4, 2));
        
        // Returns an array with the 3 decimal values [R, G, B]
        return [$red, $green, $blue];
    }

}

