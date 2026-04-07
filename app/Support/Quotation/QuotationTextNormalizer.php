<?php

namespace App\Support\Quotation;

/**
 * Light cleanup for human-entered / OCR-derived quotation text (display & approved records).
 */
final class QuotationTextNormalizer
{
    public static function spacing(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\R+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function nullableSpacing(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $out = self::spacing($value);

        return $out === '' ? null : $out;
    }
}
