<?php

declare(strict_types=1);

namespace App\Support\Supplier;

/**
 * Parses Vietnamese-style quotation header strings such as "Hoàng Yến, 0916789025" into name + phone.
 */
final class QuotationContactPersonParser
{
    /**
     * @return array{name: string, phone: ?string}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($raw === '') {
            return null;
        }

        // Split on comma, semicolon, pipe, or dash (common in headers).
        $parts = preg_split('/\s*[,，;|]\s*|\s+[–—]\s+/u', $raw, 2);
        $first = trim((string) ($parts[0] ?? ''));
        $second = isset($parts[1]) ? trim((string) $parts[1]) : '';

        if ($second !== '') {
            $digitsSecond = self::digits($second);
            if ($digitsSecond !== '' && self::looksLikeVnPhoneLength(strlen($digitsSecond))) {
                return [
                    'name' => $first !== '' ? $first : __('Contact'),
                    'phone' => self::normalizeVnPhoneDigits($digitsSecond),
                ];
            }

            return ['name' => trim($first.', '.$second), 'phone' => null];
        }

        // Single segment: try trailing phone (e.g. "Chị Lan 0912345678").
        if (preg_match('/^(.+?)\s+((?:\+?84|0)[\d\s.\-]{8,})$/u', $raw, $m)) {
            $digits = self::digits($m[2]);
            if (self::looksLikeVnPhoneLength(strlen($digits))) {
                return [
                    'name' => trim($m[1]),
                    'phone' => self::normalizeVnPhoneDigits($digits),
                ];
            }
        }

        // Phone-only line.
        $allDigits = self::digits($raw);
        if (self::looksLikeVnPhoneLength(strlen($allDigits))) {
            return [
                'name' => __('Contact'),
                'phone' => self::normalizeVnPhoneDigits($allDigits),
            ];
        }

        // Name only (no reliable phone).
        if ($first !== '') {
            return ['name' => $first, 'phone' => null];
        }

        return null;
    }

    private static function digits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?? '';
    }

    private static function looksLikeVnPhoneLength(int $len): bool
    {
        return $len >= 9 && $len <= 12;
    }

    private static function normalizeVnPhoneDigits(string $digits): string
    {
        if (str_starts_with($digits, '84') && strlen($digits) >= 10) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }
}
