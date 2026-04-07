<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Models\IngestionBatch;
use App\Models\OcrResult;
use App\Services\OCR\GoogleOcrStructuredDocumentCompiler;

/**
 * Builds ordered text lines from Google OCR: API {@see OcrResult::$raw_text} when present, else structured pages.
 */
final class OcrLayoutExtractor
{
    /**
     * @return list<string>
     */
    public function extractLines(IngestionBatch $batch): array
    {
        $batch->loadMissing(['files']);

        $lines = [];
        $files = $batch->files()->orderBy('page_order')->orderBy('id')->get();

        foreach ($files as $file) {
            /** @var OcrResult|null $ocr */
            $ocr = $file->ocrResults()->orderByDesc('id')->first();
            if ($ocr === null) {
                continue;
            }

            if (GoogleOcrStructuredDocumentCompiler::isGoogleEngine($ocr->engine_name)) {
                $apiText = trim((string) ($ocr->raw_text ?? ''));
                if ($apiText !== '') {
                    foreach ($this->splitLines($apiText) as $l) {
                        $lines[] = $l;
                    }

                    continue;
                }
            }

            $blocks = is_array($ocr->structured_blocks) ? $ocr->structured_blocks : null;
            foreach (GoogleOcrStructuredDocumentCompiler::linesFromStructuredBlocks($blocks) as $l) {
                $lines[] = $l;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function splitLines(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));
    }

    /**
     * Uses the same file-section layout as {@see QuotationExtractionService::compileOcrDocument} (Google structured OCR only).
     *
     * @return list<string>
     */
    public function linesFromCompiledDocument(string $compiledDocument): array
    {
        $compiledDocument = trim($compiledDocument);
        if ($compiledDocument === '') {
            return [];
        }

        $chunks = preg_split('/^=== .+? ===\s*$/m', $compiledDocument, -1, PREG_SPLIT_NO_EMPTY) ?: [$compiledDocument];
        $lines = [];
        foreach ($chunks as $chunk) {
            foreach ($this->splitLines($chunk) as $l) {
                $lines[] = $l;
            }
        }

        return $lines;
    }
}
