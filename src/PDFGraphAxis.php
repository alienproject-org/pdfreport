<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing axis (X or Y) with labels to be linked to a graph
 * 
 * File :       PDFGraphAxis.php
 * @version  	1.0.12 - 05/10/2026
 */
class PDFGraphAxis
{    

    /**
     * Class constructor
     */
    public function __construct(private PDFAxisSettings $settings) {
        // Auto-create-initialize all properties 
    }
    
    /*
    public function setSize(float $x1, float  $y1, float  $x2, float  $y2) 
    {
        $this->settings->setSize($x1, $y1, $x2, $y2);
    }
    */

    /**
     * Set the axis title
     * 
     * @param string $title     Axis title
     * @return PDFGraphAxis     Self reference to chain methods
     */
    public function setTitle($title) {
        $this->settings->title = $title;
        return $this;
    }
     
    /**
     * Imposta il titolo della legenda
     * 
     * @param float $height     Altezza dell'area in cui verrà inserito il titolo
     * @return PDFGraphAxis     Self reference to chain methods
     */
    public function setTitleHeight(float $height) {
        $this->settings->titleHeight = $height;
        return $this;
    }

    /**
     * Set the axis orientation
     * 
     * @param bool $isVertical  True for vertical axis, false for horizontal axis
     * @return PDFGraphAxis     Self reference to chain methods
     */
    public function setOrientationVertical($isVertical) {
        $this->settings->isVertical = (bool)$isVertical;
        return $this;
    }
        
    private function renderYAxisHorizontal(PDFReport $report)
    {
        $x1 = $this->settings->x1;
        $y1 = $this->settings->y1;
        $x2 = $this->settings->x2;
        $y2 = $this->settings->y1;
        if ($this->settings->ticksCount < 2) {
            $this->settings->ticksCount = 2;        // At least two ticks
        }
        // Draw line axis and ticks
        $report->PdfLine($x1, $y1, $x2, $y2, $this->settings->line);
        $tickGapSize = ($x2 - $x1) / ($this->settings->ticksCount - 1);
        $x = $x1;
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $report->PdfLine($x, $y1, $x, $y1 + $this->settings->tickSize, $this->settings->line);
            $x += $tickGapSize;
        }
        // Draw title
        if (strlen($this->settings->title) > 0) {
            $report->PdfBox($x1, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight,
                            $x2, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight + $this->settings->titleHeight, 
                            $this->settings->title, 
                            $this->settings->font, 'C', 'M', 0);
        }
        if (!$this->settings->isLabelVisible) return;
        // Draw labels (centered on the ticks, a label wider than labelWidth gets a wider box)
        $valueGapSize = ($this->settings->maxValue - $this->settings->minValue) / ($this->settings->ticksCount - 1);
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $labelValue = $this->settings->minValue + ($t * $valueGapSize);
            $text = $report->FormatChartValue($this->settings->valueFormat, $labelValue);
            $labelWidth = max($this->settings->labelWidth, $this->getLabelWidth($report, $text));
            $x = $x1 + ($t * $tickGapSize) - ($labelWidth / 2.0);
            // Draw label
            $report->PdfBox($x, $y1 + $this->settings->tickSize, 
                            $x + $labelWidth, $y1 + $this->settings->tickSize + $this->settings->labelHeight, 
                            $text, 
                            $this->settings->font, 'C', 'M', 0);
        }        
    }

    private function renderYAxisVertical(PDFReport $report)
    {
        $x1 = $this->settings->x2;
        $y1 = $this->settings->y1;
        $x2 = $this->settings->x2;
        $y2 = $this->settings->y2;
        if ($this->settings->ticksCount < 2) {
            $this->settings->ticksCount = 2;        // At least two ticks
        }
        // Draw line axis and ticks
        $report->PdfLine($x1, $y1, $x2, $y2, $this->settings->line);
        $tickGapSize = ($y2 - $y1) / ($this->settings->ticksCount - 1);
        $y = $y1;
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $report->PdfLine($x2 - $this->settings->tickSize, $y, $x2, $y, $this->settings->line);
            $y += $tickGapSize;
        }
        // Draw title
        if (strlen($this->settings->title) > 0) {
            /*
            $report->PdfBox($x1, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight,
                            $x2, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight + $this->settings->titleHeight, 
                            $this->settings->title, 
                            $this->settings->font, 'C', 'M', 0);
            */
        }
        if (!$this->settings->isLabelVisible) return;
        // Draw labels (a label wider than labelWidth gets a wider box, right aligned to the tick)
        $valueGapSize = ($this->settings->maxValue - $this->settings->minValue) / ($this->settings->ticksCount - 1);
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $labelValue = $this->settings->maxValue - ($t * $valueGapSize);
            $text = $report->FormatChartValue($this->settings->valueFormat, $labelValue);
            $labelWidth = $this->getLabelWidth($report, $text);
            $align = 'C';
            if ($labelWidth > $this->settings->labelWidth) {
                $align = 'R';
            } else {
                $labelWidth = $this->settings->labelWidth;
            }
            $y = $y1 + ($t * $tickGapSize) - ($this->settings->labelHeight / 2.0);
            // Draw label
            $report->PdfBox($x2 - $this->settings->tickSize - $labelWidth, $y, 
                            $x2 - $this->settings->tickSize, $y + $this->settings->labelHeight, 
                            $text, 
                            $this->settings->font, $align, 'M', 0);
        }
    }

    /**
     * Returns the width of the box needed to print a label on a single line (text width and cell paddings) with the axis font
     */
    private function getLabelWidth(PDFReport $report, string $text) : float
    {
        $paddings = $report->pdf->getCellPaddings();
        return $report->pdf->GetStringWidth($text) + $paddings['L'] + $paddings['R'] + 0.2;
    }

    private function renderXAxisHorizontal(PDFReport $report)
    {
        $x1 = $this->settings->x1;
        $y1 = $this->settings->y1;
        $x2 = $this->settings->x2;
        $y2 = $this->settings->y1;
        if ($this->settings->ticksCount == 0 && count($this->settings->dataItems) > 0) {
            $this->settings->ticksCount = count($this->settings->dataItems);
        }
        if ($this->settings->ticksCount < 2) {
            $this->settings->ticksCount = 2;        // At least two ticks
        }
        // Draw line axis and ticks
        $report->PdfLine($x1, $y1, $x2, $y2, $this->settings->line);
        if ($this->settings->tickDistance == 0)
            // Auto calculate tick gap size (same size of the bars: the space between the ticks is tickMargin)
            $tickGapSize = (($x2 - $x1) - (($this->settings->ticksCount - 1) * $this->settings->tickMargin)) / $this->settings->ticksCount;
        else
            $tickGapSize = $this->settings->tickDistance;
        $x = $x1;
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $x += ($tickGapSize / 2.0);                                 // Center tick
            $report->PdfLine($x, $y1, $x, $y1 + $this->settings->tickSize, $this->settings->line);
            $x += ($tickGapSize / 2.0) + $this->settings->tickMargin;   // Next tick position
        }
        // Draw title
        if (strlen($this->settings->title) > 0) {
            $report->PdfBox($x1, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight,
                            $x2, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight + $this->settings->titleHeight, 
                            $this->settings->title, 
                            $this->settings->font, 'C', 'M', 0);
        }
        if (!$this->settings->isLabelVisible) return;
        // Draw labels. Use data items for ticks/labels
        $x = $x1;
        foreach ($this->settings->dataItems as $dataItem) {
            // Draw label
            $x += ($tickGapSize / 2.0);                                 // Center label to tick
            $report->PdfBox($x - ($this->settings->labelWidth / 2.0), $y1 + $this->settings->tickSize, 
                            $x + ($this->settings->labelWidth / 2.0), $y1 + $this->settings->tickSize + $this->settings->labelHeight, 
                            $dataItem->label, 
                            $this->settings->font, 'C', 'M', 0);
            $x += ($tickGapSize / 2.0) + $this->settings->tickMargin;   // Next label position
        }
    }

    private function renderXAxisVertical(PDFReport $report)
    {
        $x1 = $this->settings->x2;
        $y1 = $this->settings->y1;
        $x2 = $this->settings->x2;
        $y2 = $this->settings->y2;
        if ($this->settings->ticksCount == 0 && count($this->settings->dataItems) > 0) {
            $this->settings->ticksCount = count($this->settings->dataItems);
        }
        if ($this->settings->ticksCount < 2) {
            $this->settings->ticksCount = 2;        // At least two ticks
        }
        // Draw line axis and ticks
        $report->PdfLine($x1, $y1, $x2, $y2, $this->settings->line);
        if ($this->settings->tickDistance == 0)
            // Auto calculate tick gap size (same size of the bars: the space between the ticks is tickMargin)
            $tickGapSize = (($y2 - $y1) - (($this->settings->ticksCount - 1) * $this->settings->tickMargin)) / $this->settings->ticksCount;
        else
            $tickGapSize = $this->settings->tickDistance;
        $y = $y1;
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $y += ($tickGapSize / 2.0);                                 // Center tick
            $report->PdfLine($x2 - $this->settings->tickSize, $y, $x2, $y, $this->settings->line);
            $y += ($tickGapSize / 2.0) + $this->settings->tickMargin;   // Next tick position
        }
        // Draw title
        if (strlen($this->settings->title) > 0) {
            /*
            $report->PdfBox($x1, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight,
                            $x2, $y1 + $this->settings->tickSize + $this->settings->tickSize + $this->settings->labelHeight + $this->settings->titleHeight, 
                            $this->settings->title, 
                            $this->settings->font, 'C', 'M', 0);
            */
        }
        if (!$this->settings->isLabelVisible) return;
        // Draw labels. Use data items for ticks/labels
        $y = $y1;
        foreach ($this->settings->dataItems as $dataItem) {
            // Draw label
            $y += ($tickGapSize / 2.0);                                 // Center label to tick
            $report->PdfBox($x2 - $this->settings->tickSize - $this->settings->labelWidth, $y - ($this->settings->labelHeight / 2.0), 
                            $x2 - $this->settings->tickSize, $y + ($this->settings->labelHeight / 2.0), 
                            $dataItem->label, 
                            $this->settings->font, 'C', 'M', 0);
            $y += ($tickGapSize / 2.0) + $this->settings->tickMargin;   // Next label position
        }
    }
    
    /**
     * Renders the axis
     */
    public function render(?PDFReport $report = null) : void
    {
        if ($report == null) return;
        if (!$this->settings->isVisible) return;
        if ($this->settings->font != null) {
            $report->PdfSetFont($this->settings->font);
        }
        if (count($this->settings->dataItems) > 0) {
            // X axis, use data items for ticks/labels
            if ($this->settings->isVertical)
                $this->renderXAxisVertical($report);
            else 
                $this->renderXAxisHorizontal($report);
        } else {
            // Y numeric axis
            if ($this->settings->isVertical)
                $this->renderYAxisVertical($report);
            else 
                $this->renderYAxisHorizontal($report);
        }
        $report->PdfSetDefaultFont();
    }
    
}