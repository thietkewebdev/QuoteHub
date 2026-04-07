<?php

namespace App\Support\Locale;

use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

/**
 * Presentation-only helpers for Vietnamese UI (VND + dates).
 * Use from Filament tables, infolists, and future quotation views — do not persist these strings.
 */
final class VietnamesePresentation
{
    public const DATE_FORMAT = 'd/m/Y';

    public const DATETIME_FORMAT = 'd/m/Y H:i';

    /**
     * Integer-style VND display: 282366000 → "282.366.000 đ".
     */
    public static function vnd(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        if (! is_numeric($amount)) {
            return null;
        }

        $n = (float) $amount;
        if (! is_finite($n)) {
            return null;
        }

        return number_format($n, 0, ',', '.').' đ';
    }

    /**
     * Parse a date string and format as dd/mm/yyyy for Vietnamese UI.
     *
     * - Y-m-d (ISO) → unambiguous.
     * - d/m/y or d-m-y with 1–2 digit day/month → treated as Vietnamese day/month/year (NOT US m/d).
     */
    public static function dateFromString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
                $c = Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3], config('app.timezone'));

                return $c->format(self::DATE_FORMAT);
            }

            if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $value, $m)) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                $year = (int) $m[3];
                $c = Carbon::createFromDate($year, $month, $day, config('app.timezone'));

                return $c->format(self::DATE_FORMAT);
            }

            return Carbon::parse($value)->timezone(config('app.timezone'))->format(self::DATE_FORMAT);
        } catch (Throwable) {
            return $value;
        }
    }

    public static function dateTime(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->timezone(config('app.timezone'))->format(self::DATETIME_FORMAT);
            }

            return Carbon::parse($value)->timezone(config('app.timezone'))->format(self::DATETIME_FORMAT);
        } catch (Throwable) {
            return null;
        }
    }
}
