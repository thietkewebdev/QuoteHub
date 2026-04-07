<?php

namespace App\Services\OCR;

use App\Models\IngestionBatch;
use App\Models\OcrResult;

/**
 * Builds quotation extraction input from queued Google OCR ({@see OcrResult}).
 *
 * Prefers {@see OcrResult::$raw_text} when set — that value is Google's API {@code full_text},
 * matching {@see IngestionGoogleOcrDraftService} / the admin raw OCR capture page. Falls back to
 * serializing {@see OcrResult::$structured_blocks} pages (blocks + tables) when {@code full_text} was empty.
 */
final class GoogleOcrStructuredDocumentCompiler
{
    /** @var list<string> */
    public const GOOGLE_ENGINES = ['google-document-ai', 'google-vision'];

    public static function isGoogleEngine(?string $engineName): bool
    {
        return in_array(strtolower((string) $engineName), self::GOOGLE_ENGINES, true);
    }

    /**
     * @param  array<string, mixed>|null  $structured
     */
    public static function structuredHasExtractableContent(?array $structured): bool
    {
        if (! is_array($structured)) {
            return false;
        }

        $pages = $structured['pages'] ?? [];

        return is_array($pages) && $pages !== [] && trim(self::compilePages($pages)) !== '';
    }

    /**
     * Google OCR row is usable for extraction (API full_text and/or structured pages).
     */
    public static function ocrResultHasExtractableContent(OcrResult $ocr): bool
    {
        if (! self::isGoogleEngine($ocr->engine_name)) {
            return false;
        }

        if (trim((string) ($ocr->raw_text ?? '')) !== '') {
            return true;
        }

        $structured = is_array($ocr->structured_blocks) ? $ocr->structured_blocks : null;

        return self::structuredHasExtractableContent($structured);
    }

    public static function compileBatchToDocument(IngestionBatch $batch): string
    {
        $batch->loadMissing(['files']);

        $parts = [];
        $files = $batch->files()->orderBy('page_order')->orderBy('id')->get();

        foreach ($files as $file) {
            /** @var OcrResult|null $ocr */
            $ocr = $file->ocrResults()->orderByDesc('id')->first();

            if ($ocr === null || ! self::isGoogleEngine($ocr->engine_name)) {
                continue;
            }

            $structured = is_array($ocr->structured_blocks) ? $ocr->structured_blocks : null;
            $fromApiFullText = trim((string) ($ocr->raw_text ?? ''));
            $section = $fromApiFullText !== ''
                ? $fromApiFullText
                : self::compileStructuredBlocksToDocument($structured);

            if ($section === '') {
                continue;
            }

            $parts[] = '=== '.$file->original_name.' (page_order: '.$file->page_order.') ==='."\n".$section;
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>|null  $structured
     */
    public static function compileStructuredBlocksToDocument(?array $structured): string
    {
        if (! is_array($structured)) {
            return '';
        }

        $pages = $structured['pages'] ?? [];

        return is_array($pages) && $pages !== [] ? self::compilePages($pages) : '';
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    public static function compilePages(array $pages): string
    {
        $out = [];

        foreach ($pages as $pi => $page) {
            if (! is_array($page)) {
                continue;
            }

            $out[] = '### Page '.($pi + 1);

            foreach ($page['blocks'] ?? [] as $block) {
                if (! is_array($block)) {
                    continue;
                }

                if (isset($block['paragraphs']) && is_array($block['paragraphs'])) {
                    foreach ($block['paragraphs'] as $para) {
                        if (! is_array($para)) {
                            continue;
                        }
                        $t = trim((string) ($para['text'] ?? ''));
                        if ($t !== '') {
                            $out[] = $t;
                        }
                    }
                } else {
                    $t = trim((string) ($block['text'] ?? ''));
                    if ($t !== '') {
                        $out[] = $t;
                    }
                }
            }

            foreach ($page['tables'] ?? [] as $ti => $table) {
                if (! is_array($table)) {
                    continue;
                }

                $out[] = '### Table '.($ti + 1);

                foreach ($table['rows'] ?? [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $cells = [];
                    foreach ($row as $cell) {
                        $cells[] = trim(preg_replace('/\s+/u', ' ', (string) $cell) ?? '');
                    }

                    $out[] = implode("\t", $cells);
                }
            }
        }

        return implode("\n", array_filter($out, fn (string $l): bool => $l !== ''));
    }

    /**
     * @param  array<string, mixed>|null  $structured
     * @return list<string>
     */
    public static function linesFromStructuredBlocks(?array $structured): array
    {
        $doc = self::compileStructuredBlocksToDocument($structured);
        if ($doc === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $doc) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));
    }
}
