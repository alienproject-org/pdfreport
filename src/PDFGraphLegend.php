<?php

namespace AlienProject\PDFReport;

/**
 * Legend chart class
 * 
 * File :       PDFGraphLegend.php
 * @version  	1.0.4 - 15/11/2025
 */
class PDFGraphLegend 
{    

    /**
     * Class constructor
     */
    public function __construct(private PDFLegendSettings $settings,                  
                                private ?array $items = null,
                                private ?array $measures = null) {
        // Auto-create-initialize all properties : $settings ... $items (array of ChartItems)
    }
    
    public function setSize(float $x1, float  $y1, float  $x2, float  $y2) 
    {
        $this->settings->setSize($x1, $y1, $x2, $y2);
    }

    /**
     * Set the legend title
     * 
     * @param string $title     Legend title
     * @return PDFGraphLegend   Self reference to chain methods
     */
    public function setTitle($title) {
        $this->settings->title = $title;
        return $this;
    }
     
    /**
     * Set the height of the legend title
     * 
     * @param float $height     Height of the area where the title will be printed
     * @return PDFGraphLegend   Self reference to chain methods
     */
    public function setTitleHeight(float $height) {
        $this->settings->titleHeight = $height;
        return $this;
    }

    /**
     * Set the legend orientation
     * 
     * @param bool $isVertical  True for vertical orientation, false for horizontal orientation
     * @return PDFGraphLegend   Self reference to chain methods
     */
    public function setOrientationVertical($isVertical) {
        $this->settings->isVertical = (bool)$isVertical;
        return $this;
    }
    
    /**
     * Adds an item to the legend
     * 
     * @param object $item      PDFChartItem object
     * @return PDFGraphLegend   Self reference to chain methods
     */
    public function addItem(PDFChartItem $item) {
        $this->items[] = $item;
        return $this;
    }
    

    private function renderLegendHorizontal(PDFReport $report)
    {
        $x = $this->settings->x1 + $this->settings->padding;
        $y = $this->settings->y1 + $this->settings->padding;
        $itemsCount = 0;
        if (!empty($this->items) && is_array($this->items) && count($this->items) > 0) {
            $itemsCount = count($this->items) + (strlen($this->settings->title) > 0 ? 1 : 0);
        } else if (!empty($this->measures) && is_array($this->measures) && count($this->measures) > 0) {
            $itemsCount = count($this->measures);
        }
        $itemsCount += (strlen($this->settings->title) > 0 ? 1 : 0);
        $totalPadding = ($itemsCount -1) * $this->settings->padding;
        $width = ($this->settings->x2 - $this->settings->x1 - $totalPadding) / ($itemsCount + 1);
        If ($width < 10) $width = 10;
        if (strlen($this->settings->title) > 0) 
        {
            $report->PdfBox($x, $y, $x + $width, $y + $this->settings->titleHeight, $this->settings->title, $this->settings->font, 'L', 'M', 0);
            $x += $width + $this->settings->padding;
        }
        if (!empty($this->items) && is_array($this->items) && count($this->items) > 0) {
            foreach ($this->items as $item) {
                // Draw item box 
                $report->PdfRectangle($x, $y, $x + $this->settings->boxSize, $y + $this->settings->boxSize, 0, '0000', $this->settings->line, $item->fill);
                // Draw item label
                $x += $this->settings->boxSize + $this->settings->padding;
                $label = $item->label;
                if ($this->settings->isValueVisible) {
                    // Draw item value
                    $itemValue = $item->getValue(0);
                    $label .= ' (' . $itemValue .')';
                }
                $report->PdfBox($x, $y, $x + $width, $y + $this->settings->itemLabelHeight, $label, $this->settings->font, 'L', 'M', 0);
                // Prepare to process next item
                $x += $width + $this->settings->padding;
            }
        } else {
            // Use measures
            if (!empty($this->measures) && is_array($this->measures) && count($this->measures) > 0) {
                foreach ($this->measures as $measure) {
                    // Draw legend box 
                    $report->PdfRectangle($x, $y, $x + $this->settings->boxSize, $y + $this->settings->boxSize, 0, '0000', $this->settings->line, $measure->symbol->fill);
                    // Draw legend label
                    $x += $this->settings->boxSize + $this->settings->padding;
                    $label = $measure->label;
                    $report->PdfBox($x, $y, $x + $width, $y + $this->settings->itemLabelHeight, $label, $this->settings->font, 'L', 'M', 0);
                    // Prepare to process next item
                    $x += $width + $this->settings->padding;
                }
            }
        }
    }

    private function renderLegendVertical(PDFReport $report)
    {
        $x = $this->settings->x1 + $this->settings->padding;
        $y = $this->settings->y1 + $this->settings->padding;
        if (strlen($this->settings->title) > 0) 
        {
            $report->PdfBox($x, $y, $this->settings->x2 - $this->settings->padding, $y + $this->settings->titleHeight, $this->settings->title, $this->settings->font, 'L', 'M', 0);
            $y += $this->settings->titleHeight;
        }
        if (!empty($this->items) && is_array($this->items) && count($this->items) > 0) {
            // Use dataitems
            foreach ($this->items as $item) {
                $x = $this->settings->x1 + $this->settings->padding;
                // Draw legend box 
                $report->PdfRectangle($x, $y, $x + $this->settings->boxSize, $y + $this->settings->boxSize, 0, '0000', $this->settings->line, $item->fill);
                // Draw legend label
                $x += $this->settings->boxSize + $this->settings->padding;
                $label = $item->label;
                if ($this->settings->isValueVisible) {
                    // Draw value
                    $itemValue = $item->getValue(0);
                    $label .= ' (' . $itemValue .')';
                }
                $report->PdfBox($x, $y, $this->settings->x2 - $this->settings->padding, $y + $this->settings->itemLabelHeight, $label, $this->settings->font, 'L', 'M', 0);
                // Prepare to process next item
                $y += $this->settings->itemLabelHeight + $this->settings->marginBetweenItems;
            }
        } else {
            // Use measures
            if (!empty($this->measures) && is_array($this->measures) && count($this->measures) > 0) {
                foreach ($this->measures as $measure) {
                    $x = $this->settings->x1 + $this->settings->padding;
                    // Draw legend box 
                    $report->PdfRectangle($x, $y, $x + $this->settings->boxSize, $y + $this->settings->boxSize, 0, '0000', $this->settings->line, $measure->symbol->fill);
                    // Draw legend label
                    $x += $this->settings->boxSize + $this->settings->padding;
                    $label = $measure->label;
                    $report->PdfBox($x, $y, $this->settings->x2 - $this->settings->padding, $y + $this->settings->itemLabelHeight, $label, $this->settings->font, 'L', 'M', 0);
                    // Prepare to process next item
                    $y += $this->settings->itemLabelHeight + $this->settings->marginBetweenItems;
                }
            }
        }
    }

    /**
     * Render the full legend
     */
    public function render(PDFReport $report) 
    {
        if (!$this->settings->isVisible) return;
        $itemsCount = 0;
        if (!empty($this->items) && is_array($this->items) && count($this->items) > 0) {
            $itemsCount = count($this->items) + (strlen($this->settings->title) > 0 ? 1 : 0);
        } else if (!empty($this->measures) && is_array($this->measures) && count($this->measures) > 0) {
            $itemsCount = count($this->measures);
        }
        if ($itemsCount == 0) return;
        // Draw legend container area
        $report->SetOpacity($this->settings->opacity, true, false);
        $report->PdfRectangle($this->settings->x1, $this->settings->y1, $this->settings->x2, $this->settings->y2, $this->settings->radius, '1111', $this->settings->line, $this->settings->fill);
        // Draw legend elements
        if ($this->settings->isVertical)
            $this->renderLegendVertical($report);
        else 
            $this->renderLegendHorizontal($report);
        // Resets the transparency value (alpha color) to the default
        $report->ResetOpacity();
    }
    
}