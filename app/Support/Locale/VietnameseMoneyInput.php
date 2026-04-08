<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * Parse / format money fields for Vietnamese UI: thousands ".", decimals "," (e.g. 1.080.000,5 đ).
 */
final class VietnameseMoneyInput
{
    /**
     * Normalize user or stored input to a float for persistence / calculations.
     */
    public static function parse(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $f = (float) $value;

            return is_finite($f) ? $f : null;
        }

        $s = trim((string) $value);
        $s = preg_replace('/\s+/u', '', $s) ?? '';
        $s = preg_replace('/đ|VND|vnd/ui', '', $s) ?? '';
        $s = trim($s);

        if ($s === '' || $s === '-' || $s === ',' || $s === '.') {
            return null;
        }

        if (str_contains($s, ',')) {
            $parts = explode(',', $s, 2);
            $intPart = str_replace('.', '', (string) $parts[0]);
            $intPart = preg_replace('/[^\d\-]/', '', $intPart) ?? '';
            $decPart = isset($parts[1]) ? preg_replace('/[^\d]/', '', $parts[1]) : '';

            if ($intPart === '' || $intPart === '-') {
                return null;
            }

            return $decPart !== '' ? (float) ($intPart.'.'.$decPart) : (float) $intPart;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            return (float) str_replace('.', '', $s);
        }

        if (preg_match('/^-?\d+(\.\d{1,4})?$/', $s)) {
            return (float) $s;
        }

        if (preg_match('/^-?[\d.]+$/', $s)) {
            return (float) str_replace('.', '', $s);
        }

        return null;
    }

    /**
     * Format a numeric value for display in form inputs (no "đ" suffix; use {@see TextInput::suffix()}).
     */
    public static function format(?float $value): ?string
    {
        if ($value === null || ! is_finite($value)) {
            return null;
        }

        $negative = $value < 0;
        $abs = abs($value);
        $formatted = number_format($abs, 4, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return ($negative ? '-' : '').$formatted;
    }

    public static function formatForDisplay(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = self::parse($value);
        if ($parsed === null) {
            return null;
        }

        return self::format($parsed);
    }

    /**
     * While typing in Filament/Livewire: apply Vietnamese grouping (.) when the value is parseable.
     * Skips partial input (e.g. trailing comma) so calculation logic is unchanged.
     *
     * @param  callable(string, mixed): mixed  $set  Filament Set utility (use '' path for the active field)
     */
    public static function reformatLiveState(callable $set, mixed $state): void
    {
        if ($state === null || $state === '') {
            return;
        }

        if (is_int($state) || is_float($state)) {
            $formatted = self::format((float) $state);
            if ($formatted !== null) {
                $set('', $formatted);
            }

            return;
        }

        $parsed = self::parse($state);
        if ($parsed === null) {
            return;
        }

        $formatted = self::format($parsed);
        if ($formatted === null) {
            return;
        }

        if ($formatted !== (string) $state) {
            $set('', $formatted);
        }
    }
}
