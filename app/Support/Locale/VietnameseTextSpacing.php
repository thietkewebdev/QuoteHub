<?php

namespace App\Support\Locale;

/**
 * Safe OCR spacing: Latin units after digits, punctuation before digits, optional glue-phrase map.
 */
final class VietnameseTextSpacing
{
    /**
     * NFC so glue-map keys match API/LLM output (composed vs decomposed Unicode).
     */
    public static function normalizeUnicode(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($text, \Normalizer::FORM_C);

            return is_string($n) ? $n : $text;
        }

        return $text;
    }

    /**
     * Replace known OCR-glued Vietnamese phrases (longest keys first). Map from config
     * {@see config('quotation_ai.line_text_refinement.glue_phrases')}.
     */
    public static function applyGluePhraseMap(string $text, ?array $map = null): string
    {
        if ($text === '') {
            return '';
        }

        $text = self::normalizeUnicode($text);

        $map ??= config('quotation_ai.line_text_refinement.glue_phrases', []);
        if (! is_array($map) || $map === []) {
            return $text;
        }

        $pairs = [];
        foreach ($map as $from => $to) {
            $from = (string) $from;
            if ($from === '') {
                continue;
            }
            $pairs[$from] = (string) $to;
        }

        if ($pairs === []) {
            return $text;
        }

        uksort($pairs, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($pairs as $from => $to) {
            $text = str_replace($from, $to, $text);
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public static function insertLetterDigitBoundaries(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = self::normalizeUnicode($text);

        $text = preg_replace('/([&+])(\d)/u', '$1 $2', $text) ?? $text;

        // Letter (incl. Vietnamese) immediately before 2+ ASCII digits — e.g. "giải300dpi", "đa100mm"
        // Single digit skipped so "A4", "CO2" stay intact.
        $text = preg_replace('/([\p{L}])(?=(\d{2,}))/u', '$1 ', $text) ?? $text;

        // Digit run + common Latin unit tokens
        $text = preg_replace(
            '/(\d+)(dpi|mm|kg|ml|mAh|mhz|kW|GB|MB|TB|inch|inches)\b/iu',
            '$1 $2',
            $text,
        ) ?? $text;

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
