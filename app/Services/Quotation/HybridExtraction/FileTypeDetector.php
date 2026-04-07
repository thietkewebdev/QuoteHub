<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Models\OcrResult;

/**
 * Classifies how document text was obtained (digital PDF text vs raster OCR).
 */
final class FileTypeDetector
{
    public const TEXT_PDF = 'text_pdf';

    public const SCANNED_PDF = 'scanned_pdf';

    public const IMAGE = 'image';

    public const UNKNOWN = 'unknown';

    public function detectFromOcrResult(?OcrResult $ocr, ?string $fileMimeType = null): string
    {
        if ($ocr === null) {
            return self::UNKNOWN;
        }

        $engine = strtolower((string) $ocr->engine_name);

        if (in_array($engine, ['google-document-ai', 'google-vision'], true)) {
            $mime = strtolower((string) ($fileMimeType ?? $ocr->ingestionFile?->mime_type ?? ''));

            if ($mime === 'application/pdf') {
                return self::SCANNED_PDF;
            }

            if (str_starts_with($mime, 'image/')) {
                return self::IMAGE;
            }

            return self::UNKNOWN;
        }

        return self::UNKNOWN;
    }
}
