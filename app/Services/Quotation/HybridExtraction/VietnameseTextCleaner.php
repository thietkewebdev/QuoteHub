<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Support\Locale\VietnameseTextSpacing;

/**
 * Deterministic spacing / OCR glue fixes for Vietnamese product text.
 */
final class VietnameseTextCleaner
{
    public function clean(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;

        if ((bool) config('quotation_ai.line_text_refinement.regex_letter_digit', true)) {
            $text = VietnameseTextSpacing::insertLetterDigitBoundaries($text);
        }

        if ((bool) config('quotation_ai.line_text_refinement.glue_phrases_enabled', true)) {
            $text = VietnameseTextSpacing::applyGluePhraseMap($text);
        }

        return trim($text);
    }
}
