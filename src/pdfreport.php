<?php
namespace AlienProject\PDFReport;
use TCPDF;
/*
 * Requirements:
 * - PHP Version 8.2 or greater
 * - TCPDF php library
 * 
 * https://tcpdf.org/docs/srcdoc/TCPDF/classes-TCPDF/       TCPDF ver.6.10.0 online help 
 *
 */

/**
 * PDFReport class
 *
 * PDF report generation based on XML template
 * 
 * @package  	PDFReport - Library for generating PDF documents based on XML template
 * @version  	1.0.0 - 12/08/2025							            Version number and date of last release
 * @category    PHP Class Library
 * @copyright 	2025 - Alien Project
 * @license 	https://alienproject.org/license/			            GNU license for this package
 * @author   	MaxBR8 <contact@alienproject.org>
 * @access   	public
 * 
 * @see      	https://tcpdf.org/docs/srcdoc/TCPDF/classes-TCPDF/
 * @see         https://github.com/tecnickcom/TCPDF/blob/main/examples/example_027.php
 */
class PDFReport
{
	public string $version = '1.0.0 - 12/08/2025';
    public string $xmlTemplateFileName = '';        // Transformations : XML template file name -> XML template string -> Template array
    public string $xmlTemplate = '';                // XML template string
    private $template = null;                       // Template (array format) extracted from the XML template string
    private $varList = [];                          // Associative array : Variable Key (UPPERCASE) -> Variable Value  (add variables here using the SetVar command)
    public ?TCPDF $pdf = null;
    private $pageIndex = 0;
    private $pageCount = 0;
    private $sections = [];
    private $contents = [];
    private $prevSec = null;
    private string $currentSectionId = '';          // Current section id (build in progress)
    // PDF current settings
    private ?PDFPageSettings $page = null;          // PDF current page settings
    private ?PDFFontSettings $font = null;          // PDF current font settings
    private ?PDFLineSettings $line = null;          // PDF current line settings
    private ?PDFBarcodeSettings $barcode = null;
    private ?PDFFillSettings $fill = null;          // PDF current fill settings
    private float $opacity = 1.0;                   // Current opacity/transparent (alpha color component setting)
    // Prevent infinite ricorsive calls
    private int $loopCount = 0;
    // Other settings
    private $datalist = [];                         // Datalist (the placeholders used for the graphs will be replaced with the data from the datalist)

    // ***************************

    /**
     * Class constructor. Initializes the main properties of the class.
     * 
     * @access public
     * @param string $xmlTemplateFileName	Optional. Template XML file name,
     * @return void                         No return
     */
    function __construct(string $xmlTemplateFileName = '')
    {
        $this->page = new PDFPageSettings();
        $this->font = new PDFFontSettings();
        $this->line = new PDFLineSettings();
        $this->barcode = new PDFBarcodeSettings();
        $this->fill = new PDFFillSettings();
        if (strlen($xmlTemplateFileName) > 0)
            $this->LoadTemplate($xmlTemplateFileName);        
    }

    // ***************************

    /**
     * Set a variabile key (UPPERCASE) and value. The variable key will be dinamically replaced by it's value in the report. Eg. {MYLABEL} -> "Hello word!"
     * 
     * @access public
     * @param string $varkey                Variable key, UPPERCASE format, without {}
     * @param object $varvalue              Variable value
     * @param bool $overwrite               true : if var exits overwrite it, false : if var exit do not change var value
     * @return bool                         True when variabile is been recorded successfully, false otherwise
     */
    public function SetVar($varkey, $varvalue, bool $overwrite = true)
    {
        if (strlen(trim($varkey)) > 0) {
            $varkey = strtoupper(trim($varkey));
            $varkey = str_ireplace('{', '', $varkey);
            $varkey = str_ireplace('}', '', $varkey);
            if ($overwrite || (!$overwrite && !array_key_exists($varkey, $this->varList)))
                $this->varList[$varkey] = $varvalue;
            return true;
        }
        return false;
    }

    // ***************************

    /**
     * Set all variabile key (UPPERCASE) and value from an associate array. The variable key will be dinamically replaced by it's value in the report. Eg. {MYLABEL} -> "Hello word!"
     * 
     * @access public
     * @param string $varkey                Variable key, UPPERCASE format, without {}
     * @param object $varvalue              Variable value
     * @param bool $overwrite               true : if var exits overwrite it, false : if var exit do not change var value
     * @return bool                         True when variabile is been recorded successfully, false otherwise
     */
    public function SetVars(array $varkeyValueList, bool $overwrite = true)
    {
        $okCount = 0;
        foreach ($varkeyValueList as $varkey => $varvalue) {
            if ($this->SetVar($varkey, $varvalue, $overwrite))
                $okCount++;
        }
        return ($okCount == count($varkeyValueList));
    }

    // ***************************

    /**
     * Set for a section the query used to retrive data for the report (using ? placeholders 
     * for arguments parameters) and the query parameters array
     * 
     * @access public
     * @param string $sectionId             Section Id (identity key)
     * @param object $dataProvider          Data provider (a class that implements DataProviderInterface)
     * @param PDFPageSettings $page         Page settings
     * @return PDFReportSection             PDFReportSection object
     */
    public function SetSection(string $sectionId, $dataProvider = null, $page = null) : PDFReportSection
    {
        // Add a new section or update existing section
        $section = new PDFReportSection($sectionId, $dataProvider, $page);
        $this->sections[$sectionId] = $section;
        PDFLog::Write("SetSection:[$sectionId]");
        $section->Reset();
        return $section;
    }

    // ***************************

    /**
     * Set for a datalist the query used to retrive data for the chart objects (using ? placeholders 
     * for arguments parameters) and the query parameters array
     * 
     * @access public
     * @param string $datalistId            Datalist Id (identity key)
     * @param object $dataProvider          Data provider (a class that implements DataProviderInterface)
     * @return PDFDatalist                  PDFDatalist object
     */
    public function SetDatalist(string $datalistId, $dataProvider = null) : PDFDatalist
    {
        // Add new datalist or update existing datalist
        $datalist = new PDFDatalist($datalistId, $dataProvider);
        $this->datalist[$datalistId] = $datalist;
        PDFLog::Write("SetDatalist:[$datalistId]");
        $datalist->Reset();
        return $datalist;
    }

    // ***************************

    /**
     * Set the sectionName session as the current session (called when "session" build starts)
     * Returns the total number of records to process, return 0 when no data are available 
     * 
     * @access private
     * @throws Exception 		            Raise an exception for invalid/missing section attributes
     * @param array $section                Array representing the section extracted from the XML template
     * @return PDFReportSection             PDFReportSection object
     */
    private function StartSection($section) : PDFReportSection
    {
        // Validazione Id sezione
        $sectionId = $this->LoadValue($section, 'id');
        if ($sectionId == '') {
            // Error
            throw new \Exception('PDFReport.StartSection() : Invalid XML section format, missing id attribute.');
        }
        PDFLog::Write("StartSection-Begin:[$sectionId]");
        
        $this->currentSectionId = $sectionId;
        // Check if there is a defined section, otherwise it automatically adds it to the list
        if (!array_key_exists($sectionId, $this->sections)) {
            // Add a new section without data query
            $sec = $this->SetSection($sectionId);
        } else {
            $sec = $this->sections[$sectionId];
        }
        // Set section parameters
        $sec->y_start = $this->LoadValue($section, 'y_start', $sec->y_start);               // Y position of the section start printing (where the first line is printed)
        $sec->row_height = $this->LoadValue($section, 'row_height', $sec->row_height);      // Height of a single row. Each time a new data row is read, a new print row is added until the y_end or rows_count condition is reached.
        $sec->y_end = $this->LoadValue($section, 'y_end', $sec->y_end);                     // Maximum section height. If the printed sequence of lines (rows) exceeds this, a page break is performed.
        $sec->rows_count = $this->LoadValue($section, 'rows_count', $sec->rows_count);      // Maximum lines (rows) per page counter. Alternative to y_end. When one of the two conditions is met, a page break is performed.
        
        // Section page (optional, if set a new page is created each time a new section starts)
        $section_page_node = $this->LoadNode($section, 'page', []);      
        if (count($section_page_node) > 0) {
            $sec->page = $this->ProcessPage('', $section_page_node, false);
        }

        if (is_null($sec->row)) {
            $sec->setQuery($this->ReplaceTags($sec->getQueryRaw()));
            $sec->ExecuteQuery();
        }
        PDFLog::Write("StartSection-End:[$sectionId]");
        return $sec;
    }

    // ***************************

    /**
     * Returns the current section (or null if not available)
     * 
     * @access private
     * @return object|null                  PDFReportSection object or null if section is not available
     */
    private function GetCurrentSection() : ?PDFReportSection
    {
        if (strlen($this->currentSectionId) > 0 && array_key_exists($this->currentSectionId, $this->sections))
            return $this->sections[$this->currentSectionId];
        else
            return null;
    }

    // ***************************

    /**
     * Returns true if the string contains at least one tag in the format {tag_name}
     * 
     * @access private
     * @param string $strWithTags           Source string to analyze to find out if it contains one or more tags
     * @return bool                         true: one of more tags found into source string parameter, false: no tags found
     */
    private function TagsExists($strWithTags)
    {
        return (substr_count($strWithTags, '{') > 0 && substr_count($strWithTags, '}') > 0);
    }

    // ***************************

    /**
     * Returns the source string with any tags present replaced with their current values
     * 
     * @access private
     * @param string $strWithTags           Source string with any tags to be processed
     * @return string                       Source string with any tags present replaced with their current values
     */
    private function ReplaceTags($strWithTags)
    {
        $sRet = $strWithTags;
        if (!$this->TagsExists($sRet)) return $sRet;       // No tags to replace
        // Replace CONSTANT tags
        $sRet = str_ireplace('{CURRENTDATE}', date('d/m/Y'), $sRet);
        $sRet = str_ireplace('{CURRENTTIME}', date('H:i:s'), $sRet);
        $sRet = str_ireplace('{PAGEINDEX}', $this->pageIndex, $sRet);
        $sRet = str_ireplace('{PAGECOUNT}', $this->pageCount, $sRet);
        // $sRet = str_ireplace('{ROOT}', base_path() . DIRECTORY_SEPARATOR, $sRet);
        // $sRet = str_ireplace('{CURRY}', $this->currY, $sRet);
        $sRet = str_ireplace('{RAND1}', random_int(1, 9), $sRet);
        $sRet = str_ireplace('{RAND2}', random_int(10, 99), $sRet);
        $sRet = str_ireplace('{RAND3}', random_int(100, 999), $sRet);
        $sRet = str_ireplace('{RAND4}', random_int(1000, 9999), $sRet);
        $sRet = str_ireplace('{RAND5}', random_int(10000, 99999), $sRet);
        $sRet = str_ireplace('{RAND6}', random_int(100000, 999999), $sRet);
        $sRet = str_ireplace('{RAND7}', random_int(1000000, 9999999), $sRet);
        $sRet = str_ireplace('{RAND8}', random_int(10000000, 99999999), $sRet);

        // TODO : Add more str_ireplace commands here to manage further new CONSTANT tags...

        // Replace tags using assciative array varList
        foreach ($this->varList as $varkey => $varvalue) {
            $sRet = str_ireplace('{' . $varkey . '}', $varvalue, $sRet);
        }
        if (!$this->TagsExists($sRet)) return $sRet;       // All tags resolved, exit

        // Special tag {Y} and database fields : 
        // {field_name}                         Look for field name in the current active section
        // {section_name.field_name}            Look for field name in a specific section name
        $section = $this->GetCurrentSection();
        if ($section != null) {
            $sRet = str_ireplace('{Y}', $section->CurrentY(), $sRet);
            
            $rec = $section->row;
            //$rec = $section->getCurrentRow();

            if ($rec != null) {
                foreach ($rec as $key => $value) {
                    if ($value == null || is_null($value))
                        $value = '';
                    $sRet = str_ireplace('{' . $key . '}', $value, $sRet);
                }
            }
        }
        if (!$this->TagsExists($sRet)) return $sRet;       // All tags resolved, exit
        // Look for field name in a specific section name, eg. : {invoice_row.article_code}
        foreach ($this->sections as $sectionkey => $section) {
            $rec = $section->row;
            //$rec = $section->getCurrentRow();
            
            if ($rec == null) continue;
            foreach ($rec as $fieldkey => $fieldvalue) {
                if ($fieldvalue == null || is_null($fieldvalue))
                    $fieldvalue = '';
				// Section data field
				$sRet = str_ireplace('{' . $sectionkey . '.' . $fieldkey . '}', $fieldvalue, $sRet);
            }
        }
        // Look for field name in a specific datalist using id, eg. : {ch1.label}
        foreach ($this->datalist as $datalistkey => $datalist) {
            $rec = $datalist->row;
            if ($rec == null) continue;
            foreach ($rec as $fieldkey => $fieldvalue) {
                if ($fieldvalue == null || is_null($fieldvalue))
                    $fieldvalue = '';
				// Datalist data field
				$sRet = str_ireplace('{' . $datalistkey . '.' . $fieldkey . '}', $fieldvalue, $sRet);
            }
        }
		// Look for constant name in a specific section name, eg. : {invoice_header.PAGEINDEX}
        foreach ($this->sections as $sectionkey => $section) {
			$key = '{' . $sectionkey . '.PAGEINDEX}';
			if (substr_count($strWithTags, $key) > 0) {
				$pageIndex = $section->GetPageIndex();
				$sRet = str_ireplace($key, $pageIndex, $sRet);
			}
        }
		
        return $sRet;
    }

    // ***************************

    /**
     * Convert an XML object to an associative array
     * 
     * @access private
     * @param object $xmlObject             XML object 
     * @param string $prefix	            Associative array key prefix (optional) 
     * @return array                        Associative array with XML data
     */
    private function xmlToCustomArray($xmlObject, $prefix = '') 
    {
        $array = [];
        $attributeIndex = 0;
    
        // Add element attributes
        foreach ($xmlObject->attributes() as $attrName => $attrValue) {
            $k = strtolower($prefix . $attrName . '.' . $attributeIndex);
            $array[$k] = (string)$attrValue;
            $attributeIndex++;
        }
    
        // Add text value, if present
        $textValue = trim((string)$xmlObject);
        if (!empty($textValue) || $textValue === '0') {
            $k = strtolower($prefix . 'value.' . $attributeIndex);
            $array[$k] = $textValue;
        }
    
        // Manage child nodes
        $childIndex = 1;
        foreach ($xmlObject as $key => $value) {
            $keyWithSuffix = strtolower($prefix . $key . '.' . $childIndex);
    
            if ($value->count()) {
                // If the node has children, call this function recursively
                $childArray = $this->xmlToCustomArray($value);
    
                // If the result is an array with only one "value" element, simplify
                if (count($childArray) === 1 && isset($childArray['value.0'])) {
                    $array[$keyWithSuffix] = $childArray['value.0'];
                } else {
                    $array[$keyWithSuffix] = $childArray;
                }
            } else {
                // If the node has no children, manage value and attributes
                $childArray = [];
                foreach ($value->attributes() as $childAttrName => $childAttrValue) {
                    $k = strtolower($childAttrName . '.0');
                    $childArray[$k] = (string)$childAttrValue;
                }
    
                $childTextValue = trim((string)$value);
                if (!empty($childTextValue) || $childTextValue === '0') {
                    $childArray['value.0'] = $childTextValue;
                }
    
                // If the array contains only the value, simplify
                if (count($childArray) === 1 && isset($childArray['value.0'])) {
                    $array[$keyWithSuffix] = $childArray['value.0'];
                } else {
                    $array[$keyWithSuffix] = $childArray;
                }
            }
    
            $childIndex++;
        }
    
        // If the current array has only one element "value.0", simplify
        if (count($array) === 1 && isset($array[$prefix . 'value.' . $attributeIndex])) {
            $k = strtolower($prefix . 'value.' . $attributeIndex);
            return $array[$k];
        }
    
        return $array;
    }
    
    

    // ***************************

    /**
     * Set the XML report template into private property "xmlTemplate" (xml string) and "template" array
     * 
     * @access public
     * @throws Exception 		            Raise an exception if the XML template string is missing or not valid
     * @param string $xmlTemplate			XML template string to use
     * @return void                         No return
     */
    public function SetTemplate(string $xmlTemplate = '')
    {
        PDFLog::Write("SetTemplate-Start");
        // Validating property
        if ($xmlTemplate == '')
            $xmlTemplate = trim($this->xmlTemplate);
        if (strlen($xmlTemplate) > 0)
            $this->xmlTemplate = trim($xmlTemplate);
        if (strlen($this->xmlTemplate) == 0)
            // Error
            throw new \Exception('PDFReport.SetTemplate() : Missing XML template string');
        // Set template from XML template string
        $xmlObject = simplexml_load_string($this->xmlTemplate);
        //$json = json_decode(json_encode((array)$xmlObject), true);
        //$this->template = array($xmlObject->getName() => $json);
        $this->template = [$xmlObject->getName() => $this->xmlToCustomArray($xmlObject)];

        if (!array_key_exists('pdf', $this->template))
            // Error
            throw new \Exception('PDFReport.SetTemplate() : Invalid XML template format, missing main [pdf] root key tag');
        $this->template = $this->template['pdf'];        // Get data from main root key
        PDFLog::Write("SetTemplate-End");
    }

    // ***************************

    /**
     * Load the XML report template file into private property "xmlTemplate" (xml string) and "template" array
     * 
     * @access public
     * @throws Exception 		            Raise an exception if the XML template file is missing or not readble
     * @param string $xmlTemplateFileName	XML template file name to load
     * @return void                         No return
     */
    public function LoadTemplate($xmlTemplateFileName = '')
    {
        // Validating property
        if ($xmlTemplateFileName == '')
            $xmlTemplateFileName = trim($this->xmlTemplateFileName);
        if (strlen($xmlTemplateFileName) > 0)
            $this->xmlTemplateFileName = trim($xmlTemplateFileName);
        if (strlen($this->xmlTemplateFileName) == 0)
            // Error
            throw new \Exception('PDFReport.LoadTemplate() : XML template file name not set');
        // Load XML template from file
        if (is_readable($this->xmlTemplateFileName)) {
            $xml = file_get_contents($this->xmlTemplateFileName);
            $this->SetTemplate($xml);
        } else {
            // Error
            throw new \Exception('PDFReport.LoadTemplate() : XML template file not found or not readable [' . $this->xmlTemplateFileName . ']');
        }
    }

    // ***************************

    /**
     * Creates (and automatically downloads, if required) the PDF report file
     * 
     * @access public
     * @return void                         No return
     */
    public function BuildReport()
    {
        PDFLog::Write("BuildReport-Begin");
        if ($this->template == null) {
            // Error
            throw new \Exception('PDFReport.BuildReport() : Missing XML template. Use LoadTemplate or SetTemplate before to run this method.');
        }
        $template = $this->template;
                
        // Load the "default_page" block and initialize the PDF document
		if ($this->ValueExists($template, 'default_page')) {
			$default_page_node = $this->LoadNode($template, 'default_page', []);
			$this->page->Initialize($this->LoadValue($default_page_node, 'format', 'A4'), 
                                    $this->LoadValue($default_page_node, 'orientation', 'P'), 
                                    $this->LoadValue($default_page_node, 'unit', 'mm'), 
                                    $this->LoadValue($default_page_node, 'width', 210), 
                                    $this->LoadValue($default_page_node, 'height', 297));
		}
        
        // Initiliza TCPDF with standard page size or custom page size
        $page_format = $this->page->format;
        if ($this->page->format == 'C') {
            // Custom page size
            $page_format = [ $this->page->width, $this->page->height ];
        }
        $this->pdf = new TCPDF(
            $this->page->orientation,
            $this->page->unit,
            $page_format,
            true,
            'UTF-8',
            false);
        
        $this->pdf->setAutoPageBreak(false); 
        
		if ($this->ValueExists($template, 'doc_info')) {
			$info = $this->LoadNode($template, 'doc_info', []);
			$this->pdf->SetCreator($this->LoadValue($info, 'creator', '', false, true));
			$this->pdf->SetAuthor($this->LoadValue($info, 'author', '', false, true));
			$this->pdf->SetTitle($this->LoadValue($info, 'title', '', false, true));
			$this->pdf->SetSubject($this->LoadValue($info, 'subject', '', false, true));
			$this->pdf->SetKeywords($this->LoadValue($info, 'keywords', '', false, true));
		}
		

        $this->pdf->setFontSize($this->font->size);

        // $this->pdf->SetMargins(PDF_MARGIN_LEFT, 1, PDF_MARGIN_RIGHT);
        $this->pdf->setPrintHeader(false);      // Disable automatic headers
        $this->pdf->setPrintFooter(false);

        // Load "content" blocks
        $this->contents = $this->LoadAllContents($template);
        // Process all "section" blocks (which contain references to "content" blocks to be rendered in the PDF)
        foreach ($template as $key => $element) {
            $subKey = explode('.', $key)[0];
            switch ($subKey) {
                case 'section':
                    $this->ProcessSection($template, $element);
                    break;
            }
        }
        PDFLog::Write("BuildReport-End");
    }

    // ***************************

    /**
     * Process a section that tipically contains contents to be print and, optionally, a sub-section
     * 
     * @access private
     * @throws Exception 		            Raise an exception for invalid XML template
     * @param string template			    XML section from template
     * @return void                         No return
     */
    private function ProcessSection($template, $section)
    {
        PDFLog::Write("ProcessSection-Begin");

        $sec = $this->StartSection($section);

        if ($this->prevSec != null && $this->prevSec->id == $sec->id) {
            $this->prevSec = null;
        }

        do {
            $this->loopCount++;
            if ($this->loopCount > 500) throw new \Exception('PDFReport.ProcessSection() : Recursive loop safety system activated');

            // Add new page (if required)
            $this->PdfAddPage($sec->page);
            
            // Valid "section" element (it is an array with id attribute valued and section defined), process it 
            foreach ($section as $key => $element) {
                $subKey = explode('.', $key)[0];
                switch ($subKey) {
                    case 'rem':
                    case 'comment':
                        // Do not process comments
                        break;

                    case 'id':
                    case 'y_start':
                    case 'row_height':
                    case 'y_end':
                    case 'rows_count':
                    case 'page':
                        // Valid section attributes, do nothing at this level (they are handled in : ProcessSection > StartSection)
                        break;

                    case 'print_content':
                        $this->ProcessContent($element);
                        break;
                    case 'output':
						$this->ProcessOutput($key, $template);
                        break;
                    case 'section':
                        // Process all subsections contained in the current section
                        if (is_array($element) && array_is_list($element)) {
                            // TODO : To allow Multiple nested sections a section push/pop queue must be developer to replace simple $prevSec property (previous section)
                            //foreach ($element as $sub_section)
                            //    $this->ProcessSection($template, $sub_section, $contents);
                            // Error
                            throw new \Exception('PDFReport.ProcessSection() : Multiple nested sections are not allowed, only one single section allowed within the current section [' . $sec->id . ']');
                        }
                        else
                            $this->ProcessSection($template, $element);
                        break;
                    case 'var':
                    case 'setvar':
                    case 'set_var':
                        $this->ProcessVar($key, $element);
                        break;
                    default:
                        // Error
                        throw new \Exception('PDFReport.ProcessSection() : Unsupported XML section element [' . $key . ']');
                        break;
                }
            }
            if ($this->prevSec == null || ($this->prevSec->id != $sec->id && $this->prevSec != null && $this->prevSec->EndOfData())) {
                $sec->NextRecord();  
            }
        } while (!$sec->EndOfPage());
        
        if ($sec->EndOfData()) {
            $this->prevSec = null;
        } else {
            $sec->ResetPageBreak();
            $this->prevSec = $sec;
        }

        PDFLog::Write("ProcessSection-End");
    }

    // ***************************

    /**
     * Process a content and print PDF objects like : box, line, circle, barcode, image, ...
     * 
     * @access private
     * @throws Exception 		            Raise an exception for invalid XML template
     * @param string template			    XML section from template
     * @return void                         No return
     */
    private function ProcessContent($print_content)
    {
        $id_content = $this->LoadValue($print_content, 'value', $print_content, true);
        if (!array_key_exists($id_content, $this->contents)) {
            // Error
            throw new \Exception('PDFReport.ProcessContent() : Missing XML content element [' . $id_content . ']');
        }
        $x_offset = $this->LoadValue($print_content, 'x', 0);       // Additional x and y offset (optional values)
        $y_offset = $this->LoadValue($print_content, 'y', 0);
        $sec = $this->GetCurrentSection();
        if ($sec != null) {
            $y_offset += $sec->OffsetY();                           // Adds y-offset (optional) of the current section being processed
        }
        $content = $this->contents[$id_content];
        // Process content elements (PDF document objects)
        foreach ($content as $key => $element) {
            // Manage here PDF document element generation
            // TODO : Add management of each new item here
            $subKey = explode('.', $key)[0];
            switch (strtolower($subKey)) {
                case 'rem':
                case 'comment':
                    // Do not process comments
                    break;
                case 'id':
                    // TODO : Process all attributes
                    break;
                case 'page':
                    // Add a new page to the document
                    $this->ProcessPage($key, $element, true);
                    break;
                case 'line':
                    $this->ProcessLine($key, $element, $x_offset, $y_offset);
                    break;
                case 'text':
					// Deprecated : replaced by box which has many more features
                    break;
                case 'box':
                    $this->ProcessBox($key, $element, $x_offset, $y_offset);
                    break;
                case 'rectangle':
                case 'rect':
                    $this->ProcessRectangle($key, $element, $x_offset, $y_offset);
                    break;
                case 'piechart':
                    $this->ProcessPieChart($key, $element, $x_offset, $y_offset);
                    break;
                case 'singlebarchart':
                    $this->ProcessSingleBarChart($key, $element, $x_offset, $y_offset);
                    break;
                case 'gaugechart':
                    $this->ProcessGaugeChart($key, $element, $x_offset, $y_offset);
                    break;
                case 'kpichart':
                    $this->ProcessKpiChart($key, $element, $x_offset, $y_offset);
                    break;
                case 'font':
					$this->font = $this->ProcessFont($key, $element);
					$this->PdfSetFont($this->font);
                    break;
                case 'linestyle':
                    $this->ProcessLineStyle($key, $element, true, true);
                    break;
                case 'circle':
                    $this->ProcessCircle($key, $element, $x_offset, $y_offset);
                    break;
                case 'barcode':
                    $this->ProcessBarcode($key, $element, $x_offset, $y_offset);
                    break;
                case 'image':
                    $this->ProcessImage($key, $element, $x_offset, $y_offset);
                    break;
                case 'fill':
                    $this->ProcessFill($key, $element, true, true);
                    break;
                case 'opacity':
                case 'alphacolor':
                case 'alpha':
                    $this->ProcessOpacity($key, $element);
                    break;
                case 'var':
                case 'setvar':
                case 'set_var':
                    $this->ProcessVar($key, $element);
                    break;
                default:
                    // Error
                    throw new \Exception('PDFReport.ProcessContent() : Unsupported XML content element [' . $key . ']');
                    break;
            }
        }
    }
	
	// ***************************

    /**
     * Generate PDF file
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with output settings
     * @return void                         No return
     */
	private function ProcessOutput($key, $element)
	{
		$fname = 'document_' . date('Ymd_His') . '.pdf';
		$fname = $this->LoadValue($element, 'name|filename', $fname);
		$dest = $this->LoadValue($element, 'dest|destination', 'I');
		$this->pdf->Output($fname, $dest);
	}
	
    // ***************************

    /**
     * Generate PDF file
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with alpha color value (1=opaque, ... ,0=transparent)
     * @return void                         No return
     */
	private function ProcessOpacity($key, $element) : void
    {
		$alpha = $this->LoadValue($element, 'value', 1.0, true);
        if ($alpha < 0.0) $alpha = 0.0;
        if ($alpha > 1.0) $alpha = 1.0;
        $this->opacity = $alpha;
		$this->pdf->SetAlpha($alpha);
	}

	// ***************************
	
    /**
     * Add a new page
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with page settings
     * @return PDFPageSettings              Returns the PDFPageSettings object (if a setting is not found, the default page setting will be used)
     */
	private function ProcessPage($key, $element, $addPageToDocument = true)
	{
        $format = $this->LoadValue($element, 'format', $this->page->format);
		$orientation = $this->LoadValue($element, 'orientation', $this->page->orientation);
		$unit = $this->LoadValue($element, 'unit', $this->page->unit);
        $width = $this->LoadValue($element, 'width', $this->page->width);                       // width and height for custom format
        $height = $this->LoadValue($element, 'height', $this->page->height);
        $page = new PDFPageSettings($format, $orientation, $unit, $width, $height);
        if ($addPageToDocument) {
            $this->PdfAddPage($page);
        }
        return $page;
	}

    // ***************************

    /**
     * Print a new line
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with line settings (style and position)
     * @return void                         No return
     */
	private function ProcessLine($key, $element, $x_offset, $y_offset)
	{
        // Line position
		$x1 = $this->LoadValue($element, 'x1', 0, true) + $x_offset;                // x1..y2 : Required
		$y1 = $this->LoadValue($element, 'y1', 0, true) + $y_offset;   
		$x2 = $this->LoadValue($element, 'x2', 0, true) + $x_offset;
		$y2 = $this->LoadValue($element, 'y2', 0, true) + $y_offset;
		
		// Line style : width, color, dash, ...
        $line = $this->line;
        $linestyle_element = $this->LoadValue($element, 'linestyle', []);
        if (count($linestyle_element) > 0) {
            $line = $this->ProcessLineStyle('', $linestyle_element, false, false);
		}
		
		/* Orientation:
			HorizontallyTop		- HT  
			HorizontallyBottom	- HB
			VerticallyLeft		- VL
			VerticallyRight		- VR
			DiagonallyBackward	- DB (\) - Default
			DiagonallyForward	- DF (/)
		*/
		$orientation = strtolower($this->LoadValue($element, 'orientation', 'DB', false));      // Optional 
		switch ($orientation)
		{
			case 'horizontallytop':
			case 'ht':
				$this->PdfLine($x1, $y1, $x2, $y1, $line);
				break;
			case 'horizontallybottom':
			case 'hb':
				$this->PdfLine($x1, $y2, $x2, $y2, $line);
				break;
			case 'verticallyleft':
			case 'vl':
				$this->PdfLine($x1, $y1, $x1, $y2, $line);
				break;		
			case 'verticallyright':
			case 'vr':
				$this->PdfLine($x2, $y1, $x2, $y2, $line);
				break;
			case 'diagonallyforward':
			case 'df':
				$this->PdfLine($x1, $y2, $x2, $y1, $line);
				break;	
			default:
				// DiagonallyBackward - DB
				$this->PdfLine($x1, $y1, $x2, $y2, $line);
				break;
		}
		
	}

	// ***************************
	
    /**
     * Print a new box (rectangle) with text (optional) inside. Text exceeding the containing rectangle is automatically cut off.
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with box settings (style, position and text)
     * @return void                         No return
     */
	private function ProcessBox($key, $element, $x_offset, $y_offset)
	{
		//var_dump($key);
		//var_dump($element);
		//die();
		
		// Font (optional, use default font if custom font is not set)
		$font = $this->font;
		$font_element = $this->LoadValue($element, 'font', []);
		if (count($font_element) > 0) {
			$font = $this->ProcessFont('', $font_element);
		}
		
		// Text
		$text = $this->LoadValue($element, 'text', '', false, true);        // {xxx}
		if (is_array($text)) 
			$text = '';
		
		// Text Align (horizontal / vertical)
		$horizalign = $this->LoadValue($element, 'align|textalign|texthorizalign', 'L');		// L - left, C - center, R - right, J - justify
		$vertalign = $this->LoadValue($element, 'vertalign|textvertalign', 'T');				// T - top , M - middle, B - bottom
		
		// Border
		$x1 = $this->LoadValue($element, 'x1', 0, true) + $x_offset;
		$y1 = $this->LoadValue($element, 'y1', 0, true) + $y_offset;
		$x2 = $this->LoadValue($element, 'x2', 0, true) + $x_offset;
		$y2 = $this->LoadValue($element, 'y2', 0, true) + $y_offset;
		$border = $this->LoadValue($element, 'border', 1);
		
		// Line style : width, color, dash, ...
        $line = $this->line;
        $linestyle_element = $this->LoadValue($element, 'linestyle', []);
        if (count($linestyle_element) > 0) {
            $line = $this->ProcessLineStyle('', $linestyle_element, false, false);
		}
		
		// Fill style and color/s
		$fill = $this->fill;
		$fill_element = $this->LoadValue($element, 'fill', []);
        if (count($fill_element) > 0) {
            $fill = $this->ProcessFill('', $fill_element, false, false);
		}
		
		// Print box
		$this->PdfBox($x1, $y1, $x2, $y2, $text, $font, $horizalign, $vertalign, $border, $line, $fill);
		
	}

    // ***************************
	
    /**
     * Print a pie/ring chart
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with pie chart settings (style, position, data sector, ..)
     * @return void                         No return
     */
	private function ProcessPieChart($key, $element, $x_offset, $y_offset) : void
	{
		//var_dump($key);
		//var_dump($element);
		//die();
		
		// Font (optional, use default font if custom font is not set)
        /*
		$font = $this->font;
		$font_element = $this->LoadValue($element, 'font', []);
		if (count($font_element) > 0) {
			$font = $this->ProcessFont('', $font_element);
		}
		*/

		// Pie chart container, chart size (radius) and style
		$x1 = $this->LoadValue($element, 'x1', 0, true);            // x1,y1,x2,y2 : pie chart container area
		$y1 = $this->LoadValue($element, 'y1', 0, true);            // (apply x_offset and y_offset after legend initialization)
		$x2 = $this->LoadValue($element, 'x2', 0, true);
		$y2 = $this->LoadValue($element, 'y2', 0, true);
        $border = $this->LoadValue($element, 'border', 0);
        $radius = $this->LoadValue($element, 'r|radius', 0);                    // r=0 : Auto calculate PieChart radius to fill container area
		$style = strtoupper($this->LoadValue($element, 'style', 'DONUTS'));     // DONUTS = Ring style
        
        // Legend settings
        $legendSettings = new PDFLegendSettings(0, 0, 0, 0, 0, false);
        $legend_element = $this->LoadValue($element, 'legend', []);     // legend settings
		if (count($legend_element) > 0) {
			$legendSettings = $this->ProcessLegend('', $legend_element, $x_offset, $y_offset, $x1, $y1, $x2, $y2);
		}
        
        // Adjust chart container position offset
        $x1 += $x_offset;
		$y1 += $y_offset;
		$x2 += $x_offset;
		$y2 += $y_offset;

        // Chart data items
        $dataItems = $this->LoadDataItems($element);

		// PieChart
		$chart = new PDFPieChart($x1, $y1, $x2, $y2, $radius, $border, $style, $legendSettings, $dataItems);
        $chart->render($this);
	}

    // ***************************
	
    /**
     * Print a gauge chart
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with gauge chart settings (style, position, data value, ..)
     * @return void                         No return
     */
	private function ProcessGaugeChart($key, $element, $x_offset, $y_offset) : void
	{
		// Title font (optional, use default font if custom font is not set)
        $titleFont = $this->font;
		$font_element = $this->LoadValue($element, 'titlefont', []);
		if (count($font_element) > 0) {
			$titleFont = $this->ProcessFont('', $font_element);
		}	
        
		// Gauge chart container, chart size (radius) and style
		$x1 = $this->LoadValue($element, 'x1', 0, true);            // x1,y1,x2,y2 : chart container area
		$y1 = $this->LoadValue($element, 'y1', 0, true);            // (apply x_offset and y_offset after chart initialization)
		$x2 = $this->LoadValue($element, 'x2', 0, true);
		$y2 = $this->LoadValue($element, 'y2', 0, true);
        $border = $this->LoadValue($element, 'border', 0);
        $radius = $this->LoadValue($element, 'r|radius', 0);                    // r=0 : Auto calculate chart radius to fill container area
		$style = strtoupper($this->LoadValue($element, 'style', 'DONUTS'));     // DONUTS = Ring style (default)
        $title = $this->LoadValue($element, 'title', '');

        // Chart data value
        $value = $this->LoadValue($element, 'value', 0.0, true);                // Gauge value
        $minValue = $this->LoadValue($element, 'minvalue', 0.0, true);          // Gauge min value
        $maxValue = $this->LoadValue($element, 'maxvalue', 100.0, true);        // Gauge max value

        // Legend settings
        $legendSettings = new PDFLegendSettings(0, 0, 0, 0, 0, false);
        $legend_element = $this->LoadValue($element, 'legend', []);     // legend settings
		if (count($legend_element) > 0) {
			$legendSettings = $this->ProcessLegend('', $legend_element, $x_offset, $y_offset, $x1, $y1, $x2, $y2);
		}

        // Segments settings
        $segments = [];
        $segments_element = $this->LoadValue($element, 'segmentlist', []);
        if (is_array($segments_element) && count($segments_element) > 0)
        {
            foreach ($segments_element as $segment) {
                $label = $this->LoadValue($segment, 'label', '', false);
                $fillColor = $this->LoadValue($segment, 'fillcolor', $this->randomHexColor(), true);
                $startValue = $this->LoadValue($segment, 'startvalue', 0.0, true);
                $endValue = $this->LoadValue($segment, 'endvalue', 100.0, true);
                $fill = new PDFFillSettings('S', $fillColor);
                $font_segment = $this->LoadValue($segment, 'font', []);
                $font = $this->ProcessFont('', $font_segment, $this->font);
                $symbol = $this->LoadValue($segment, 'symbol', '', false);
                $segments[] = new PDFChartSegment($label, $startValue, $endValue, $fill, $font, $symbol);
            }
        } else {
            // No segments defined, use one single default segment
            $fill = new PDFFillSettings('S', '009999');
            $text = new PDFFontSettings('helvetica', 'B', 14, '006666'); 
            $segments[] = new PDFChartSegment('', $minValue, $maxValue, $fill, $text);
        }
        
        // Adjust chart container position offset
        $x1 += $x_offset;
		$y1 += $y_offset;
		$x2 += $x_offset;
		$y2 += $y_offset;

        // Chart data items
        //$dataItems = $this->LoadDataItems($element);

		// Render chart
        $chart = new PDFGaugeChart($x1, $y1, $x2, $y2, $title, $titleFont, $radius, $border, $style, $minValue, $maxValue, $value, $segments);
        $chart->render($this);
	}

    // ***************************
	
    /**
     * Print a kpi chart
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with kpi chart settings (title, position, data value, ..)
     * @return void                         No return
     */
	private function ProcessKpiChart($key, $element, $x_offset, $y_offset) : void
	{
		// Title font (optional, use default font if custom font is not set)
        $titleFont = $this->font;
		$font_element = $this->LoadValue($element, 'titlefont', []);
		if (count($font_element) > 0) {
			$titleFont = $this->ProcessFont('', $font_element);
		}	
        
		// Kpi chart container, radius, title, ...
		$x1 = $this->LoadValue($element, 'x1', 0, true);            // x1,y1,x2,y2 : chart container area
		$y1 = $this->LoadValue($element, 'y1', 0, true);            // (apply x_offset and y_offset after chart initialization)
		$x2 = $this->LoadValue($element, 'x2', 0, true);
		$y2 = $this->LoadValue($element, 'y2', 0, true);
        $border = $this->LoadValue($element, 'border', 0);
        $radius = $this->LoadValue($element, 'r|radius', 0);
		$title = $this->LoadValue($element, 'title', '');

        // Chart data value
        $value = $this->LoadValue($element, 'value', 0.0, true);    // Kpi value
        
        // Segments settings (optional)
        $segments = [];
        $segments_element = $this->LoadValue($element, 'segmentlist', []);
        if (is_array($segments_element) && count($segments_element) > 0)
        {
            foreach ($segments_element as $segment) {
                $label = $this->LoadValue($segment, 'label', '', false);
                $fillColor = $this->LoadValue($segment, 'fillcolor', $this->randomHexColor(), true);
                $startValue = $this->LoadValue($segment, 'startvalue', 0.0, true);
                $endValue = $this->LoadValue($segment, 'endvalue', 100.0, true);
                $fill = new PDFFillSettings('S', $fillColor);
                $font_segment = $this->LoadValue($segment, 'font', []);
                $font = $this->ProcessFont('', $font_segment, $this->font);
                $symbol = $this->LoadValue($segment, 'symbol', '', false);
                $segments[] = new PDFChartSegment($label, $startValue, $endValue, $fill, $font, $symbol);
            }
        } 

        // Adjust chart container position offset
        $x1 += $x_offset;
		$y1 += $y_offset;
		$x2 += $x_offset;
		$y2 += $y_offset;

		// Render chart
        $chart = new PDFKpiChart($x1, $y1, $x2, $y2, $title, $titleFont, $radius, $border, $value, $segments);
        $chart->render($this);
	}

    // ***************************
	
    private function randomHexColor() {
        // Genera tre valori casuali per R, G, B (da 0 a 255)
        $r = mt_rand(0, 255);
        $g = mt_rand(0, 255);
        $b = mt_rand(0, 255);

        // Converte ogni valore in formato esadecimale e lo formatta a due cifre
        $hexR = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $hexG = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $hexB = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        // Concatena i valori esadecimali per ottenere il colore completo
        return $hexR . $hexG . $hexB;
    }

    // ***************************

    /**
     * Print a new single bar chart.
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with chart settings (style, position, data items, ..)
     * @return void                         No return
     */
	private function ProcessSingleBarChart($key, $element, $x_offset, $y_offset) : void
	{
		// Font (optional, use default font if custom font is not set)
		$font = $this->font;
		$font_element = $this->LoadValue($element, 'font', []);
		if (count($font_element) > 0) {
			$font = $this->ProcessFont('', $font_element);      // Title font
		}

		// Single bar chart container, chart size and style (eg. orientation vertical or horizontal)
		$x1 = $this->LoadValue($element, 'x1', 0, true);            // x1,y1,x2,y2 : single bar chart container area
		$y1 = $this->LoadValue($element, 'y1', 0, true);            // (apply x_offset and y_offset after legend initialization)
		$x2 = $this->LoadValue($element, 'x2', 0, true);
		$y2 = $this->LoadValue($element, 'y2', 0, true);
        $minValue = $this->LoadValue($element, 'minvalue', 0);
        $maxValue = $this->LoadValue($element, 'maxvalue', 0);      // 0 = Autoscale to total data items value
        //$border = $this->LoadValue($element, 'border', 0);
        $orientation = strtoupper($this->LoadValue($element, 'orientation', 'horizontal'));     // h / horiz / horizontal, v / vert / vertical
        $isVertical = str_starts_with(strtolower($orientation), 'v');
        $title = $this->LoadValue($element, 'title', '');

        // Legend settings
        $legendSettings = new PDFLegendSettings(0, 0, 0,0, 0, false);
        $legend_element = $this->LoadValue($element, 'legend', []);     // legend settings
		if (count($legend_element) > 0) {
			$legendSettings = $this->ProcessLegend('', $legend_element, $x_offset, $y_offset, $x1, $y1, $x2, $y2);
		}
        
        // Adjust chart container position offset
        $x1 += $x_offset;
		$y1 += $y_offset;
		$x2 += $x_offset;
		$y2 += $y_offset;

        // Chart data items
        $dataItems = $this->LoadDataItems($element);

		// SingleBarChart
		$chart = new PDFSingleBarChart($x1, $y1, $x2, $y2, $isVertical, $minValue, $maxValue, $title, $font, $legendSettings, $dataItems);
        $chart->render($this);
	}

    // ***************************

    /**
     * Gets an array of PDFChartItem from a <datalist> node
     */
    private function LoadDataItems($element) 
    {
        // Chart data items
        $dataItems = [];
		$dataList = $this->LoadValue($element, 'datalist', []);
        $datalistId = $this->loadValue($dataList, 'id', '');
        if ($datalistId == '') {
            // No valid data source, use static values in XML code
            if (is_array($dataList) && count($dataList) > 0) {
                foreach ($dataList as $data) {
                    if (is_array($data)) {
                        $item = new PDFChartItem($this->LoadValue($data, 'label', '', true),
                                                    $this->LoadValue($data, 'value', 0, true), 
                                                    0, 
                                                    new PDFFillSettings('S', $this->LoadValue($data, 'color', $this->randomHexColor(), true)));
                        $dataItems[] = $item;
                    }
                }
            }
        } else {
            // Dinamic datalist, load data values from db ()
            $dtlist = null;
            if (array_key_exists($datalistId, $this->datalist))
                $dtlist = $this->datalist[$datalistId]; 
            if ($dtlist != null && is_array($dataList) && count($dataList) > 0) {
                $data = $this->LoadValue($dataList, 'data', [], true);
                $label = $this->LoadValue($data, 'label', '', true, true);      // label, value, color {xxx}
                $value = $this->LoadValue($data, 'value', 0, true, true);
                $color = $this->LoadValue($data, 'color', '', true, true);
                // It cycles through the possible values and replaces the {xxx} placeholders with data loaded from the database
                $dtlist->Reset();
                if ($dtlist->ExecuteQuery() > 0) {
                    while (!$dtlist->EndOfData()) {
                        $item = new PDFChartItem($label,
                                                $value, 
                                                0, 
                                                new PDFFillSettings('S', $color));
                        $dataItems[] = $item;
                        $dtlist->NextRecord();
                    }
                }    
            }
        }		 
        if (count($dataItems) == 0) {
            // Error - No data set found
            throw new \Exception('PDFReport.LoadDataItems() : No data set found. Missing datalist or valid data tag.');
        }
        return $dataItems;
    }

    // ***************************
	
    /**
     * Print a new rectangle with rounded corners (optional), without text inside.
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with rectangle settings (style, position, border, fill, ..)
     * @return void                         No return
     */
	private function ProcessRectangle($key, $element, $x_offset, $y_offset)
	{
		// Border
		$x1 = $this->LoadValue($element, 'x1', 0, true) + $x_offset;
		$y1 = $this->LoadValue($element, 'y1', 0, true) + $y_offset;
		$x2 = $this->LoadValue($element, 'x2', 0, true) + $x_offset;
		$y2 = $this->LoadValue($element, 'y2', 0, true) + $y_offset;
        $r = $this->LoadValue($element, 'r|radius', 0, false) + $y_offset;
		$border = $this->LoadValue($element, 'border', '1111');                // 1=show border, 0=no border  (4 values : left, top, right, bottom)
		
        // Line style : width, color, dash, ...
        $line = $this->line;
        $linestyle_element = $this->LoadValue($element, 'linestyle', []);
        if (count($linestyle_element) > 0) {
            $line = $this->ProcessLineStyle('', $linestyle_element, false, false);
		}
		
		// Fill style and color/s
		$fill = $this->fill;
		$fill_element = $this->LoadValue($element, 'fill', []);
        if (count($fill_element) > 0) {
            $fill = $this->ProcessFill('', $fill_element, false, false);
		}

		// Print rectangle
		$this->PdfRectangle($x1, $y1, $x2, $y2, $r, $border, $line, $fill);

	}

    // ***************************
	
    /**
     * Return the line style, optionally apply it to the PDF document and optionally set is as default.
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with line settings (style)
     * @return PDFLineSettings              Line style settings object
     */
	private function ProcessLineStyle($key, $element, $applySettings = false, $setAsDefault = false) : PDFLineSettings
	{
		/*                    
		  Line style. Array with keys among the following:
			width (float): Width of the line in user units.
			cap (string): Type of cap to put on the line. Possible values are: butt, round, square. The difference between "square" and "butt" is that "square" projects a flat end past the end of the line.
			join (string): Type of join. Possible values are: miter, round, bevel.
			dash (mixed): Dash pattern. Is 0 (without dash) or string with series of length values, which are the lengths of the on and off dashes. For example: "2" represents 2 on, 2 off, 2 on, 2 off, ...; "2,1" is 2 on, 1 off, 2 on, 1 off, ...
			phase (integer): Modifier on the dash pattern which is used to shift the point at which the pattern starts.
			color (array): Draw color. Format: array(GREY) or array(R,G,B) or array(C,M,Y,K) or array(C,M,Y,K,SpotColorName).
		*/
        $lineSettings = new PDFLineSettings();
		$lineSettings->width = $this->LoadValue($element, 'linewidth|width', $this->line->width);
		$lineSettings->cap = $this->LoadValue($element, 'linecap|cap', $this->line->cap);
		$lineSettings->join = $this->LoadValue($element, 'linejoin|join', $this->line->join);
		$lineSettings->dash = $this->LoadValue($element, 'linedash|dash', $this->line->dash);
		$lineSettings->phase = $this->LoadValue($element, 'linephase|phase', $this->line->phase);
		$lineSettings->rgbColor = $this->LoadValue($element, 'linecolor|color', $this->line->rgbColor);       // RGB hex format, eg. FF0000 (red)
		if ($applySettings)
            $this->PdfSetLineStyle($lineSettings);
        if ($setAsDefault)
            $this->line = $lineSettings;
        return $lineSettings;
	}
	
    // ***************************
	
    /**
     * Gets the legend settings
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with legend settings (...)
     * @return PDFLegendSettings            Return an object
     */
	private function ProcessLegend($key, $element, $x_offset, $y_offset, $defaultX1, $defaultY1, $defaultX2, $defaultY2) : PDFLegendSettings
	{
        // Legend settings
        $position = strtoupper($this->LoadValue($element, 'position', 'BOTTOM'));       // Legend position : N/NONE, T/TOP, B/BOTTOM, R/RIGHT, L/LEFT
        $orientation = strtoupper($this->LoadValue($element, 'orientation', 'HORIZ'));  // H/HORIZ/HORIZONTAL, V/VERT/VERTICAL
        $isVertical = str_starts_with($orientation, 'V');
        $x1 = $this->LoadValue($element, 'x1', $defaultX1) + $x_offset;
        $y1 = $this->LoadValue($element, 'y1', $defaultY1) + $y_offset;
		$x2 = $this->LoadValue($element, 'x2', $defaultX2) + $x_offset;
		$y2 = $this->LoadValue($element, 'y2', $defaultY2) + $y_offset;
		$radius = $this->LoadValue($element, 'r|radius', 0);
        $visible = strtolower($this->LoadValue($element, 'visibile', 'true'));      // 1,true,yes,on = legend is visible / 0,false,no,off = legend is hidden
        $isVisible = ($visible == '1' || $visible == 'true' || $visible == 'yes' || $visible == 'on');
        $opacity = $this->LoadValue($element, 'opacity', 1.0);                      // 0..1
        if ($opacity < 0.0) $opacity = 0.0;
        if ($opacity > 1.0) $opacity = 1.0;

        // Title text
        $title = $this->LoadValue($element, 'title', '', true);
        
        // Font for title and labels (optional, use default font if custom font if not set)
		$font = $this->font;
		$font_element = $this->LoadValue($element, 'font', []);
		if (count($font_element) > 0) {
			$font = $this->ProcessFont('', $font_element);
		}

        // Line style : width, color, dash (optional, use default line settings if not set)
        $line = $this->line;
        $linestyle_element = $this->LoadValue($element, 'linestyle', []);
        if (count($linestyle_element) > 0) {
			$line = $this->ProcessLineStyle('', $linestyle_element, false, false);
		}

        // Fill style and color/s
		$fill = $this->fill;
		$fill_element = $this->LoadValue($element, 'fill', []);
        if (count($fill_element) > 0) {
            $fill = $this->ProcessFill('', $fill_element, false, false);
		}

        // TODO : Process new additional properties here...

        $settings = new PDFLegendSettings($x1, $y1, $x2, $y2, $radius, $isVisible, $opacity, $title, $font, $isVertical, $line, $fill);
        return $settings;
	}

	// ***************************
	
    /**
     * Set the current font style
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with font settings (family, style, size, color)
     * @param PDFFontSettings $defaultFont  Default font settings to use if font setting is missing 
     * @return PDFFontSettings              Return a PDFFontSettings object
     */
	private function ProcessFont($key, $element, $defaultFont = null) : PDFFontSettings
	{
        if ($defaultFont == null) {
            // Use default font settings
            $defaultFont = $this->font;
        }   
		$font = new PDFFontSettings($defaultFont->family, $defaultFont->style, $defaultFont->size, $defaultFont->rgbColor);
		$font->family = $this->LoadValue($element, 'fontfamily|family', $font->family);
		$font->style =  $this->LoadValue($element, 'fontstyle|style', $font->style);
		$font->size =  $this->LoadValue($element, 'fontsize|size', $font->size);
		$font->SetRGBColor($this->LoadValue($element, 'fontcolor|color', $font->rgbColor));       // RGB hex format
		return $font;
	}
	
	// ***************************
	
    /**
     * Print a new circle
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with circle settings (style, position, size)
     * @return void                         No return
     */
	private function ProcessCircle($key, $element, $x_offset, $y_offset)
	{
		$x = $this->LoadValue($element, 'x', 0, true) + $x_offset;
		$y = $this->LoadValue($element, 'y', 0, true) + $y_offset;
		$r  = $this->LoadValue($element, 'r', 0, true);
		
		// Angle start-end
		$angstart = $this->LoadValue($element, 'angstart', 0.0, false);
		$angend  = $this->LoadValue($element, 'angend', 360.0, false);
		
        // Line style : width, color, dash, ...
        $lineUpdated = false;
        $linestyle_element = $this->LoadValue($element, 'linestyle', []);
        if (count($linestyle_element) > 0) {
            $lineUpdated = true;
            $this->ProcessLineStyle('', $linestyle_element, $lineUpdated, false);
		}
		
		$this->pdf->Circle($x, $y, $r, $angstart, $angend);
		
		if ($lineUpdated) {
			// Restore default line settings
			$this->PdfSetLineStyle($this->line);
		}
	}
	
	// ***************************
	
    /**
     * Print a new barcode
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with barcode settings (style, position, size)
     * @return void                         No return
     */
	private function ProcessBarcode($key, $element, $x_offset, $y_offset)
	{
		$this->barcode->x = $this->LoadValue($element, 'x', 0) + $x_offset;
		$this->barcode->y = $this->LoadValue($element, 'y', 0) + $y_offset;
		$this->barcode->width = $this->LoadValue($element, 'width', $this->barcode->width);
		$this->barcode->height = $this->LoadValue($element, 'height', $this->barcode->height);
		$this->barcode->align = $this->LoadValue($element, 'align', $this->barcode->align);
		$this->barcode->type = $this->LoadValue($element, 'type', $this->barcode->type);
		$value = $this->LoadValue($element, 'value', $this->barcode->value, true);              // {xxx}
		$this->barcode->value = $value; 
		$this->pdf->write1DBarcode($this->barcode->value, $this->barcode->type, 
								   $this->barcode->x, $this->barcode->y, $this->barcode->width, $this->barcode->height, $this->barcode->xres,
								   $this->barcode->GetStyle());
	}
	
	// ***************************
	
    /**
     * Print a new image (loaded from file)
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with image settings (style, position, size)
     * @return void                         No return
     */
	private function ProcessImage($key, $element, $x_offset, $y_offset)
	{
		// public Image(string $file[, float|null $x = null ][, float|null $y = null ][, float $w = 0 ][, float $h = 0 ][, string $type = '' ][, mixed $link = '' ][, string $align = '' ][, mixed $resize = false ][, int $dpi = 300 ][, string $palign = '' ][, bool $ismask = false ][, mixed $imgmask = false ][, mixed $border = 0 ][, mixed $fitbox = false ][, bool $hidden = false ][, bool $fitonpage = false ][, bool $alt = false ][, array<string|int, mixed> $altimgs = array() ]) : mixed|false
		$file = $this->LoadValue($element, 'file', '', true);
		$x = $this->LoadValue($element, 'x|x1', 0, true) + $x_offset;
		$y = $this->LoadValue($element, 'y|y1', 0, true) + $y_offset;
        if ($this->ValueExists($element, 'width') && $this->ValueExists($element, 'height')) {
            $width = $this->LoadValue($element, 'width', 30);
		    $height = $this->LoadValue($element, 'height', 20);
        } else if (($this->ValueExists($element, 'x2') && $this->ValueExists($element, 'y2'))) {
            $x2 = $this->LoadValue($element, 'x2', 0) + $x_offset;
		    $y2 = $this->LoadValue($element, 'y2', 0) + $y_offset;
            $width = $x2 - $x;
            $height = $y2 - $y;
        } else {
            // Error - missing required argument 
            throw new \Exception('PDFReport.ProcessImage() : Missing required argumnets [width,height or x2,y2]');
        }
        $align = $this->LoadValue($element, 'align', '');
        $palign = $this->LoadValue($element, 'palign', '');
        $border = $this->LoadValue($element, 'border', '0');
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'png':
            case 'gif':
            case 'jpg':
            case 'jpeg':    
            case 'bmp':
                $this->pdf->Image($file, $x, $y, $width, $height, 'PNG', '', $align, true);
                break;
            case 'svg':
                $this->pdf->ImageSVG($file, $x, $y, $width, $height, '', $align, $palign, $border, true);
                break;
            default:
                // Error : Unsupported image type
                throw new \Exception('PDFReport.ProcessImage() : Unsupported image format [' . $ext . ']');
                break;
        }
		
	}
	
	// ***************************
	
    /**
     * Set the current fill style
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with fill settings (style)
     * @return PDFFillSettings              Fill style settings object
     */
	private function ProcessFill($key, $element, $applySettings = false, $setAsDefault = false) : PDFFillSettings
	{
        /*                    
          Fill style
            type (string): Type of fill. Possible values are: S - Solid, G - Gradient, P - Pattern.
            rgbColor1 (sting): Start color of the fill. Format : #RRGGBB. Required for solid and gradient fills.
            rgbColor2 (string): End color of the fill. Format : #RRGGBB. Optional for solid fills, required for gradient fills.
        */
        
		$type = trim(strtoupper($this->LoadValue($element, 'type', $this->fill->type)));
		$rgbColor1 = $this->LoadValue($element, 'startcolor|color|color1', $this->fill->rgbColor1, true, true);         // startcolor. Required always. {xxx}
		$rgbColor2 = $this->LoadValue($element, 'endcolor|color2', $this->fill->rgbColor2, false, true);                // endcolor (for gradient) {xxx}
		$fillSettings = new PDFFillSettings($type, $rgbColor1, $rgbColor2);
        if ($applySettings) {
            if ($fillSettings->type == 'S') {
                // Solid fill
                $rgb = $this->fill->GetStartColor();
                $this->pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]);
            }
        }
        if ($setAsDefault) {
            $this->fill = $fillSettings;
        }
        return $fillSettings;
	}
	
    // ***************************
	
    /**
     * Set a variable value by it's name (if exists, only when overwrite = 1, Y, Yes, T, True)
     * 
     * @access private
     * @param string $key			        Element key
     * @param string $element			    Associative array element with variable settings (name and value)
     * @return void                         No return
     */
	private function ProcessVar($key, $element)
	{
		$name = $this->LoadValue($element, 'name', '', true);                   // Name and value are required
		$value = $this->LoadValue($element, 'value', '', true);
        $overwrite = $this->LoadValue($element, 'overwrite', 'Yes', false);
        $true_values = ['1', 'y', 'yes', 't', 'true'];
        $overwriteAsBool = in_array(strtolower($overwrite), $true_values);
		$this->SetVar($name, $value, $overwriteAsBool);
	}

    // ***************************

    private function PdfAddPage(?PDFPageSettings $page)
    {
        if (is_null($page)) return;
        $this->pdf->AddPage($page->orientation, $page->format);
        $this->pageIndex++;
        $this->pageCount++;
    }

    // ***************************

    private function PdfSetLineStyle(PDFLineSettings $line)
    {
        $style = $line->GetStyle();
        $this->pdf->setLineStyle($style);
    }

    // ***************************

    private function PdfSetFont(PDFFontSettings $font)
    {
        // Font family : helvetica (helvetica/b/i/bi), times (times/b/i/bi), courier (courier/b/i/bi), symbol, zapfdingbats
        $this->pdf->SetFont($font->family, $font->style, $font->size);
        $rgb = $font->GetColor();
        $this->pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    }

    // ***************************

    public function PdfLine(float $x1, float $y1, float $x2, float $y2, ?PDFLineSettings $line = null)
    {
		$lineUpdated = false;
		if ($line != null && !$this->line->IsEqualTo($line)) {			
			$this->PdfSetLineStyle($line);
			$lineUpdated = true;
		}
		
        $this->pdf->Line($x1, $y1, $x2, $y2);
	
		if ($lineUpdated) {
			// Restore default line settings
			$this->PdfSetLineStyle($this->line);
		}
    }

    // ***************************

	public function PdfBox(float $x1, float $y1, float $x2, float $y2, string $text = '', ?PDFFontSettings $font = null, $horizalign = 'L', $vertalign = 'M', $border = 1, ?PDFLineSettings $line = null, ?PDFFillSettings $fill = null)
    {
        $this->pdf->setXY($x1, $y1);
        $width = abs($x2 - $x1);
        $height = abs($y2 - $y1);

		$lineUpdated = false;
		if ($line != null && !$this->line->IsEqualTo($line)) {
			// Custom line settings (border line) for this box
			$this->PdfSetLineStyle($line);
			$lineUpdated = true;
		}
		$fontUpdated = false;
		if ($font != null && !$this->font->IsEqualTo($font)) {
			// Custom font for this box
			$this->PdfSetFont($font);
			$fontUpdated = true;
		}
		
        $max_rows = 1;
        while ($this->pdf->getStringHeight($width, $text) > ($max_rows * $height)) {
            $text = substr($text, 0, -1);       // Rimuove un carattere alla volta
            if (strlen($text) == 0) break;      // Se la stringa viene completamente cancellata, provare ad aumentare le dimenisoni del box (altezza/larghezza)
        }

		$horizalign = $this->NormalizeHorizontalTextAlignment($horizalign);		// Horizontal text alignment
		$vertalign = $this->NormalizeVerticalTextAlignment($vertalign);			// Vertical text aligment
		
        $fillCell = false;
        if ($fill != null) {
            switch ($fill->type) {
                case 'S':
                    // S -Solid fill
                    $fillCell = true;
                    $rgb = $fill->GetStartColor();
                    $this->pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]);
                    break;
                case 'L':
				case 'G':
                    // L- Linear gradient
                    $this->pdf->LinearGradient($x1, $y1, $width, $height, $fill->GetStartColor(), $fill->GetEndColor());
                    break;
                case 'R':
                    // R - Radial gradient
                    $this->pdf->RadialGradient($x1, $y1, $width, $height, $fill->GetStartColor(), $fill->GetEndColor());
                    break;
            }			
            $this->pdf->MultiCell($width, $height, $text, $border, $horizalign, $fillCell, 1, $x1, $y1, true, 0, false, true, $height, $vertalign);
            // Reset fill color
            if ($fillCell) {
                $rgb = $this->fill->GetStartColor();
                $this->pdf->setFillColor($rgb[0], $rgb[1], $rgb[2]);               
            }
        } else {
            $this->pdf->MultiCell($width, $height, $text, $border, $horizalign, $fillCell, 1, $x1, $y1, true, 0, false, true, $height, $vertalign);
        } 

        //$this->pdf->Cell($width, $height, $text, $border, 0, $horizalign, $fill);
		
		if ($lineUpdated) {
			// Restore default line settings
			$this->PdfSetLineStyle($this->line);
		}
		if ($fontUpdated) {
			// Restore current font settings
			$this->PdfSetFont($this->font);
		}
    }

    // ***************************

	public function PdfRectangle(float $x1, float $y1, float $x2, float $y2, float $r, string $border = '1111', ?PDFLineSettings $line = null, ?PDFFillSettings $fill = null)
    {
        $this->pdf->setXY($x1, $y1);
        $width = abs($x2 - $x1);
        $height = abs($y2 - $y1);

		$lineUpdated = false;
		if ($line != null && !$this->line->IsEqualTo($line)) {
			// Custom line settings (border line) for this box
			$this->PdfSetLineStyle($line);
			$lineUpdated = true;
		}
			
        $rgb = [];
        $fillStyle = '';
        if ($line instanceof PDFLineSettings && $line->width > 0)
        {
            $fillStyle = 'D';   // D: Draw border
            $this->PdfSetLineStyle($line);
        }            
        if ($fill != null && $fill->type == 'S') {
            // S -Solid fill
            $rgb = $fill->GetStartColor();
            $fillStyle .= 'F';                  // D : draw border, F : fill with color
        } 

        $this->pdf->RoundedRect($x1, $y1, $width, $height, $r, $border, $fillStyle, [], $rgb);
        
        // Restore default setting
		if ($lineUpdated) {
			// Restore default line settings
			$this->PdfSetLineStyle($this->line);
		}
        if ($line instanceof PDFLineSettings && $line->width > 0)
        {
            $this->PdfSetLineStyle($this->line);
        }     
    }

    // ***************************

    private function LoadAllContents($template)
    {
        $contents = [];
        $element = $this->LoadAllNodes($template, 'content');

        if (is_array($element) && array_is_list($element)) {
            foreach ($element as $content) {
                $id = strtolower($this->LoadValue($content, 'id'));
                if (strlen($id) > 0)
                    $contents[$id] = $content;
            }
        } 

        return $contents;
    }

    // ***************************

    private function LoadNode($template, $nodeKey, $default = null)
    {
        foreach ($template as $key => $element) {
            $subKey = explode('.', $key)[0];
            if ($subKey == strtolower($nodeKey)) {
                return $element;
            }
        }
		if ($default != null || is_array($default)) return $default;
        if (!array_key_exists($nodeKey, $template)) {
            // Error : The node is missing
            throw new \Exception('PDFReport.LoadNode() : Missing XML node element [' . $nodeKey . ']');
        }
    }

    // ***************************

    private function LoadAllNodes($template, $nodeKey)
    {
        $nodes = [];
        foreach ($template as $key => $element) {
            $subKey = explode('.', $key)[0];
            if ($subKey == $nodeKey) {
                $nodes[] = $element;
            }
        }
        return $nodes;
    }

    // ***************************

    private function ValueExists($template, $nodeKey)
    {
        if (is_array($template)) {
            foreach ($template as $key => $element) {
                $subKey = explode('.', $key)[0];
				$nodeKeys = explode('|', $nodeKey);
				for ($t = 0; $t < count($nodeKeys); $t++) {
					if ($subKey == $nodeKeys[$t]) {
						return true;
					}
				}
            }
        } 
        return false;
    }

    // ***************************

	/**
	 * $nodeKey		eg. "color", "width|linewidth", ..
	 */
    private function LoadValue($template, $nodeKey, $default = '', $required = false, $doReplaceTags = false)
    {
        if (is_array($template)) {
            foreach ($template as $key => $element) {
                $subKey = explode('.', $key)[0];
				$nodeKeys = explode('|', $nodeKey);
				for ($t = 0; $t < count($nodeKeys); $t++) {
					if ($subKey == $nodeKeys[$t]) {
						if (is_array($element) && count($element) == 0)
							// Empty value, return default
							return $default;
						else {
                            // Found target element
                            if ($doReplaceTags)
                                return $this->ReplaceTags($element);
                            else
                                return $element;
                        }
					}
				}
            }
        } 
        if ($required && empty($default)) {
            // Error : The node is missing
            throw new \Exception('PDFReport.LoadValue() : Missing XML node element [' . $nodeKey . ']');
        }
        return $default;
    }
	
	// ***************************
	
	private function NormalizeHorizontalTextAlignment(string $align): string 
	{
		$alignMap = [
			'r' => 'R',
			'right' => 'R',
			
			'c' => 'C',
			'center' => 'C',
			'm' => 'C',
			'mid' => 'C',
			'middle' => 'C',
			
			'j' => 'J',
			'justification' => 'J',
			'justify' => 'J',
			
			'l' => 'L',
			'left' => 'L',
		];
		return $alignMap[strtolower(trim($align))] ?? 'L';
	}

	// ***************************
	
	private function NormalizeVerticalTextAlignment(string $align): string 
	{
		$alignMap = [
			't' => 'T',
			'top' => 'T',
			
			'm' => 'M',
			'mid' => 'M',
			'middle' => 'M',
			'cen' => 'M',
			'center' => 'M',
			
			'b' => 'B',
			'bot' => 'B',
			'bottom' => 'B'
		];
		return $alignMap[strtolower(trim($align))] ?? 'T';
	}
	
    // ***************************

    public function SetOpacity(float $opacity)
    {
        if ($opacity < 0.0) $opacity = 0.0;
        if ($opacity > 1.0) $opacity = 1.0;
        $this->opacity = $opacity;
    }

    // ***************************

    public function GetOpacity() : float
    {
        return $this->opacity;
    }

    // ***************************

    /**
     * Adjusts the brightness of a color.
     *
     * @param string $hex The color in hexadecimal format (e.g., "ffcc00").
     * @param int $amount The amount to add or subtract (from -255 to 255).
     * Positive values make the color lighter, negative values make it darker.
     * @return string The new color in hexadecimal format.
     */
    function adjustBrightnessColor($hex, $amount) {
        // Remove the leading '#' if present
        $hex = str_replace('#', '', $hex);

        // Check for a 3-digit hex code and expand it to 6 digits
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Extract the RGB values
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Add the amount to each RGB channel and clamp the values between 0 and 255
        $r = max(0, min(255, $r + $amount));
        $g = max(0, min(255, $g + $amount));
        $b = max(0, min(255, $b + $amount));

        // Convert the RGB values back to hexadecimal and format them
        return str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
             . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
             . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}

