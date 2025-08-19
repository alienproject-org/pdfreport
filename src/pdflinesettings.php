<?php
namespace AlienProject\PDFReport;
use TCPDF;


/**
 * PDFLineSettings class
 *
 */
class PDFLineSettings
{
    // PDF line settings
    public float $width = 0.2;             // Width (float) of the line in user units.
    public string $cap = 'butt';           // Type of cap (string) to put on the line. Possible values are: butt, round, square. The difference between "square" and "butt" is that "square" projects a flat end past the end of the line.
    public string $join = 'miter';         // Type of join (string). Possible values are: miter, round, bevel.
    public string $dash = '0';             // Dash pattern (string). Is 0 (without dash) or string with series of length values, which are the lengths of the on and off dashes. For example: "2" represents 2 on, 2 off, 2 on, 2 off, ...; "2,1" is 2 on, 1 off, 2 on, 1 off, ...
    public int $phase = 0;                 // Modifier on the dash pattern which is used to shift the point at which the pattern starts.
    public string $rgbColor = '000000';    // Color RGB hex format (000000 : black)
    private array $color = [0, 0, 0];      // RGB array color [ R, G, B ], RGB : 0..255
    

    function __construct($width = 0.2, $rgbColor = '000000', $dash = '0', $cap = 'butt', $join = 'miter')
    {
        $this->Initialize($width, $rgbColor, $dash, $cap, $join);
    }

    public function Initialize($width = 0.2, $rgbColor = '000000', $dash = '0', $cap = 'butt', $join = 'miter')
    {
        $this->width = $width;
        $this->cap = $cap;
        $this->join = $join;
        $this->dash = $dash;
        $this->rgbColor = $rgbColor;
        $this->color = $this->hexToRgbArray($rgbColor);
    }

    public function GetStyle() 
    {
        $this->color = $this->hexToRgbArray($this->rgbColor);
        $style = [
            'width' => $this->width,
            'cap' => $this->cap,
            'join' => $this->join,
            'dash' => $this->dash,
            'phase' => $this->phase,
            'color' => $this->color
        ];
        return $style;
    }

    public function GetColor()
    {
        $this->color = $this->hexToRgbArray($this->rgbColor);
        return $this->color;
    }

	public function IsEqualTo(PDFLineSettings $line) 
	{
		return ($this->width == $line->width && 
				$this->cap == $line->cap && 
				$this->join == $line->join && 
				$this->dash == $line->dash && 
				$this->phase == $line->phase && 
				$this->hexToRgbArray($this->rgbColor) == $this->hexToRgbArray($line->rgbColor));
	}
	
    private function hexToRgbArray(string $hexColor): array 
    {
        // Removes the # character at the beginning of the text, if present
        $hexColor = ltrim($hexColor, '#');
        
        // Make sure the string is 6 characters long
        if (strlen($hexColor) !== 6) {
            throw new \InvalidArgumentException('PDFReport.hexToRgbArray : The color must be expressed in hexadecimal format, 6 characters long');
        }
        
        // Extracts the red, green and blue components
        $red = hexdec(substr($hexColor, 0, 2));
        $green = hexdec(substr($hexColor, 2, 2));
        $blue = hexdec(substr($hexColor, 4, 2));
        
        // Returns an array with the 3 decimal values [R, G, B]
        return [$red, $green, $blue];
    }
}

