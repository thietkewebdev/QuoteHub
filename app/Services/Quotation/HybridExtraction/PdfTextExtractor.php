<?php

namespace App\Services\Quotation\HybridExtraction;

use Smalot\PdfParser\Parser;

/**
 * Embedded text extraction from digital PDFs (no OCR).
 */
final class PdfTextExtractor
{
    public function extractEmbeddedText(string $absolutePdfPath): ?string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePdfPath);
            $text = $pdf->getText();

            return trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
