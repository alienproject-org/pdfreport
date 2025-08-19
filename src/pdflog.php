<?php
namespace AlienProject\PDFReport;
use TCPDF;

/**
 * PDFLog Static class
 * - Enable LOG to file for live debugging
 */
class PDFLog
{
    public static bool $enabled = false;
    public static string $logFileName = 'app.log';

    public static function Write($message)
    {
        if (!PDFLog::$enabled) return;
        $dt = date('Y-m-d h:i:s');
        error_log($dt . ' - ' . $message . "\n", 3, PDFLog::$logFileName);
    }
}

