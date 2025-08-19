<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * PDFBarcodeSettings class
 *
 */
class PDFBarcodeSettings
{
    // PDF barcode settings
    public float $x = 0;
    public float $y = 0;
    public float $width = 30;
    public float $height = 10;
    public float $xres = 0.4;
    public string $align = 'C';                     // Align : C-Center, L-Left, R-Right
    public string $type = 'C39';                    // Barcode type (eg. C39, C93, C128, EAN8, EAN13, CODE11, PHARMA, ..). 
													// Full type list : https://tcpdf.org/docs/srcdoc/TCPDF/classes-TCPDFBarcode/
    public string $value = '';                      // Barcode value (number or string)
    public string $fontFamily = 'helvetica';        // Font family : helvetica, times, courier, symbol, zapfdingbats
    public float $fontSize = 9.0;
    public string $rgbColor = '000000';             // Color RGB hex format (000000 : black)
    private array $color = [0, 0, 0];               // RGB array color [ R, G, B ], RGB : 0..255
    public string $rgbBackColor = 'FFFFFF';         // Background color RGB hex format
    private array $backColor = [255, 255, 255];     // Background RGB array color [ R, G, B ]
    
    function __construct($align = 'C', $type = 'C39', $value = '', $fontFamily = 'helvetica', $fontSize = 9.0, $rgbColor = '000000', $rgbBackColor = 'FFFFFF')
    {
        $this->Initialize($align, $type, $value, $fontFamily, $fontSize,$rgbColor, $rgbBackColor);
    }

    public function Initialize($align = 'C', $type = 'C39', $value = '', $fontFamily = 'helvetica', $fontSize = 9.0, $rgbColor = '000000', $rgbBackColor = 'FFFFFF')
    {
        $this->align = $align;
        $this->type = $type;
        $this->value = $value;
        $this->fontFamily = $fontFamily;
        $this->fontSize = $fontSize;
        $this->rgbColor = $rgbColor;
        $this->rgbBackColor = $rgbBackColor;
        $this->color = $this->hexToRgbArray($rgbColor);
        $this->backColor = $this->hexToRgbArray($rgbBackColor);
    }

    public function GetStyle() 
    {
        $this->color = $this->hexToRgbArray($this->rgbColor);
        $this->backColor = $this->hexToRgbArray($this->rgbBackColor);
        $style = array(
            'position' => '',
            'align' => $this->align,
            'stretch' => false,
            'fitwidth' => false,
            'cellfitalign' => '',
            'border' => true,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => $this->color,
            'bgcolor' => $this->backColor,
            'text' => true,
            'font' => $this->fontFamily,
            'fontsize' => $this->fontSize,
            'stretchtext' => 1
        );
        return $style;
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

