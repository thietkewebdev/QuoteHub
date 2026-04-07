<?php

namespace App\Support\Quotation;

/**
 * Display-only formatters for quotation line numeric fields (tables, infolists).
 */
final class QuotationLinePresentation
{
    /**
     * @param  mixed  $state
     */
    public static function quantity($state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }
        if (! is_numeric($state)) {
            return (string) $state;
        }

        $formatted = number_format((float) $state, 4, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * @param  mixed  $state
     */
    public static function percent($state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }
        if (! is_numeric($state)) {
            return null;
        }

        $formatted = number_format((float) $state, 4, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',').'%';
    }

    /**
     * Total line amount including VAT: stored line_total is before tax; multiply by (1 + VAT%/100) when VAT is set.
     *
     * @param  mixed  $lineTotalExcl
     * @param  mixed  $vatPercent
     */
    public static function lineTotalIncludingVat($lineTotalExcl, $vatPercent): ?float
    {
        if ($lineTotalExcl === null || $lineTotalExcl === '') {
            return null;
        }
        if (! is_numeric($lineTotalExcl)) {
            return null;
        }

        $excl = (float) $lineTotalExcl;

        if ($vatPercent === null || $vatPercent === '' || ! is_numeric($vatPercent)) {
            return round($excl, 4);
        }

        $vat = (float) $vatPercent;

        return round($excl * (1 + $vat / 100), 4);
    }
}
