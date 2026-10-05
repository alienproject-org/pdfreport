<?php

namespace AlienProject\PDFReport;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * XML file data provider class: the data rows are the XML elements selected by an XPath expression (the "query").
 *
 * The file is read once, when the data provider is created; the XPath is evaluated each time the section starts,
 * after the placeholders are replaced: so a detail section can select the elements nested in the current master element.
 * A relative file path is resolved from the current working directory (as the template file).
 *
 * Fields of a row (selected element):
 *      - the attributes of the element                         <line code="A01" qty="2">           -> code, qty
 *        (a child element with the same name of an attribute replaces it)
 *      - the child elements that contain only text             <line><price>10.50</price></line>   -> price
 *        (the first one when a name is repeated; child elements with other elements inside are ignored)
 *      - "value": the text of the element, when it has no child elements      <tag id="1">PHP</tag>  -> id, value
 *
 * Options:
 *      namespaces      Prefixes used in the XPath expression (array prefix => namespace URI)
 *
 * Example:
 *      <orders>
 *          <order number="10100" customer="Blue Harbor Logistics">
 *              <line code="WEB-UX" qty="1"><description>Website redesign</description><price>4800</price></line>
 *          </order>
 *      </orders>
 *
 *      $report->SetSection('ord', new DataProviderXML('orders.xml', '/orders/order'));
 *      $report->SetSection('ord_det', new DataProviderXML('orders.xml', '/orders/order[@number="{ord.number}"]/line'));
 *
 * File :       DataProviderXML.php
 * @version  	1.0.12 - 05/10/2026
 */
class DataProviderXML extends DataProviderArray
{
    private DOMXPath $xpath;
    private string $file;

    public function __construct(string $file, string $xpath, array $options = [])
    {
        parent::__construct([], $xpath);
        $this->file = $file;
        $this->xpath = $this->loadXml($file);
        foreach ($options['namespaces'] ?? [] as $prefix => $uri) {
            $this->xpath->registerNamespace($prefix, $uri);
        }
    }

    public function execute(): void
    {
        $this->reset();
        if (trim($this->query) == '') {
            throw new \Exception('DataProviderXML: missing XPath expression for [' . $this->file . ']');
        }
        $nodes = @$this->xpath->query($this->query);
        if ($nodes === false) {
            throw new \Exception('DataProviderXML: invalid XPath expression [' . $this->query . ']');
        }
        foreach ($nodes as $node) {
            $this->filteredRows[] = $node instanceof DOMElement ? $this->elementToRow($node) : [ 'value' => trim((string)$node->nodeValue) ];
        }
    }

    private function loadXml(string $file): DOMXPath
    {
        $content = $this->readDataFile($file);
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET : no network access (external entities / DTD)
        $loaded = $doc->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA);
        $error = libxml_get_last_error();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new \Exception('DataProviderXML: invalid XML in [' . $file . ']' . ($error ? ', line ' . $error->line . ': ' . trim($error->message) : ''));
        }
        return new DOMXPath($doc);
    }

    // Attributes + child elements with text only (+ "value" for an element without child elements)
    private function elementToRow(DOMElement $element): array
    {
        $row = [];
        foreach ($element->attributes as $attribute) {
            $row[$attribute->nodeName] = $attribute->nodeValue;
        }
        $hasChildElements = false;
        $childNames = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) continue;
            $hasChildElements = true;
            if (isset($childNames[$child->nodeName])) continue;        // Repeated name: the first one
            $childNames[$child->nodeName] = true;
            $onlyText = true;
            foreach ($child->childNodes as $grandChild) {
                if ($grandChild instanceof DOMElement) {
                    $onlyText = false;
                    break;
                }
            }
            if ($onlyText) $row[$child->nodeName] = trim($child->textContent);
        }
        if (!$hasChildElements) {
            $text = trim($element->textContent);
            if ($text != '' || count($row) == 0) $row['value'] = $text;
        }
        return $row;
    }
}
