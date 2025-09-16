<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * PDFFontSettings class
 *
 * @version  	1.0.1 - 16/09/2025
 */
class PDFFontSettings
{
    // PDF font settings
    public string $family = 'helvetica';            // Font family : helvetica (helvetica/b/i/bi), times (times/b/i/bi), courier (courier/b/i/bi), symbol, zapfdingbats
    public string $style = '';						// Font style  : Empty string (regular), B: bold, I: italic, U: underline, D: line through, O: overline
    public float $size = 9.0;						// Font size (points)
    public string $rgbColor = '000000';             // Color RGB hex format (000000 : black)
    private array $color = [0, 0, 0];               // RGB array color [ R, G, B ], RGB : 0..255

    function __construct($family= 'helvetica', $style = '', $size = 9.0, $rgbColor = '000000')
    {
        $this->Initialize($family, $style, $size, $rgbColor);
    }

    public function Initialize($family= 'helvetica', $style = '', $size = 9.0, $rgbColor = '000000')
    {
        $this->family = $family;
        $this->style = $style;
        $this->size = $size;
        $this->SetRGBColor($rgbColor);
    }

    public function SetRGBColor(string $rgbColor) 
    {
        $this->rgbColor = $rgbColor;
        $this->color = $this->hexToRgbArray($rgbColor);
    }

    public function GetColor()
    {
        $this->color = $this->hexToRgbArray($this->rgbColor);
        return $this->color;
    }

	public function IsEqualTo(PDFFontSettings $font) 
	{
		return ($this->family == $font->family && 
				$this->style == $font->style && 
				$this->size == $font->size &&
				$this->hexToRgbArray($this->rgbColor) == $this->hexToRgbArray($font->rgbColor));
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

