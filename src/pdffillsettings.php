<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * PDFFillSettings class
 *
 * @version  	1.0.1 - 16/09/2025
 */
class PDFFillSettings
{
    // PDF page settings
    public string $type = 'S';                      // N-None, S-solid, L-linear gradient, R-radial gradient
    public string $rgbColor1 = 'FFFFFF';            // Color RGB hex format (FFFFFF : white)
    public string $rgbColor2 = 'FFFFFF';            
    private array $color1 = [0, 0, 0];              // RGB array color [ R, G, B ], RGB : 0..255
    private array $color2 = [0, 0, 0];


    function __construct($type = 'S', $rgbColor1 = 'FFFFFF', $rgbColor2 = 'FFFFFF')
    {
        $this->Initialize($type, $rgbColor1, $rgbColor2);
    }

    public function Initialize($type = 'S', $rgbColor1 = 'FFFFFF', $rgbColor2 = 'FFFFFF')
    {
        // Validate and fix type
        $type = substr(strtoupper(trim($type)), 0, 1);
        if (!in_array($type, ['N', 'S', 'L', 'R'])) {
            // In case of invalid fill type code, set default type to NONE
            $type = 'N';
        }
        $this->type = $type;
        $this->rgbColor1 = $rgbColor1;
        $this->color1 = $this->hexToRgbArray($rgbColor1);
        $this->rgbColor2 = $rgbColor2;
        $this->color2 = $this->hexToRgbArray($rgbColor2);
    }

    public function GetStartColor()
    {
        $this->color1 = $this->hexToRgbArray($this->rgbColor1);
        return $this->color1;
    }

    public function GetEndColor()
    {
        $this->color2 = $this->hexToRgbArray($this->rgbColor2);
        return $this->color2;
    }

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

