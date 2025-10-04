<?php

namespace AlienProject\PDFReport;

/**
 * Class for managing axis with labels to be linked to a graph
 * 
 * File :       PDFGraphAxis.php
 * @version  	1.0.2 - 04/10/2025
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
        
    private function renderAxisHorizontal(PDFReport $report)
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
        // Draw labels
        $labelValue = $this->settings->minValue;
        $valueGapSize = ($this->settings->maxValue - $this->settings->minValue) / ($this->settings->ticksCount - 1);
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $x = $x1 + ($t * $tickGapSize) - ($this->settings->labelWidth / 2.0);
            // Draw label
            $report->PdfBox($x, $y1 + $this->settings->tickSize, 
                            $x + $this->settings->labelWidth, $y1 + $this->settings->tickSize + $this->settings->labelHeight, 
                            $labelValue, 
                            $this->settings->font, 'C', 'M', 0);
            $labelValue += $valueGapSize;
        }        
    }

    private function renderAxisVertical(PDFReport $report)
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
        // Draw labels
        $labelValue = $this->settings->maxValue;
        $valueGapSize = ($this->settings->maxValue - $this->settings->minValue) / ($this->settings->ticksCount - 1);
        for ($t = 0; $t < $this->settings->ticksCount; $t++) {
            $y = $y1 + ($t * $tickGapSize) - ($this->settings->labelHeight / 2.0);
            // Draw label
            $report->PdfBox($x2 - $this->settings->tickSize - $this->settings->labelWidth, $y, 
                            $x2 - $this->settings->tickSize, $y + $this->settings->labelHeight, 
                            $labelValue, 
                            $this->settings->font, 'C', 'M', 0);
            $labelValue -= $valueGapSize;
        }
    }

    /**
     * Renders the axis
     */
    public function render(?PDFReport $report = null) : void
    {
        if ($report == null) return;
        if (!$this->settings->isVisible) return;
        if ($this->settings->isVertical)
            $this->renderAxisVertical($report);
        else 
            $this->renderAxisHorizontal($report);
    }
    
}