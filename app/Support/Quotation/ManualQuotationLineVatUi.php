<?php

namespace App\Support\Quotation;

use App\Support\Locale\VietnameseMoneyInput;

/**
 * Keeps line VAT display fields in sync for manual / review quotation repeaters.
 *
 * Persisted semantics: unit_price and line_total are both before VAT (excl.).
 * VAT amount is rounded to whole VND when derived from %; staff may override VAT amount for rounding rules.
 * Line total incl. VAT can be entered to back-calculate excl. subtotal and VAT (whole VND).
 */
final class ManualQuotationLineVatUi
{
    /**
     * @param  callable(string, mixed): void  $set
     * @param  callable(string): mixed  $get
     */
    public static function sync(callable $set, callable $get, bool $subtotalFromQtyUnitPrice): void
    {
        $q = self::toFloat($get('quantity'));
        $p = self::toFloat($get('unit_price'));

        $subtotalFromQtyPrice = ($q > 0 && $p > 0) ? round($q * $p, 4) : null;

        if ($subtotalFromQtyUnitPrice && $subtotalFromQtyPrice !== null) {
            $set('line_total', $subtotalFromQtyPrice);
            $lt = $subtotalFromQtyPrice;
        } else {
            $lt = self::toNullableFloat($get('line_total'));
        }
        $vat = self::toNullableFloat($get('vat_percent'));

        if ($lt === null) {
            $set('vat_amount_display', null);
            $set('line_gross_display', null);

            return;
        }

        if ($vat !== null) {
            $vatAmt = round($lt * $vat / 100, 0);
            $set('vat_amount_display', $vatAmt);
            $set('line_gross_display', round($lt + $vatAmt, 0));
        } else {
            $set('vat_amount_display', null);
            $set('line_gross_display', $lt);
        }
    }

    /**
     * User entered total incl. VAT and VAT %: derive excl. subtotal (whole VND), residual VAT, and unit_price from qty.
     *
     * @param  callable(string, mixed): void  $set
     * @param  callable(string): mixed  $get
     */
    public static function applyInclusiveGross(callable $set, callable $get): void
    {
        $gross = self::toNullableFloat($get('line_gross_display'));
        $vatPct = self::toNullableFloat($get('vat_percent'));
        if ($gross === null || $gross <= 0 || $vatPct === null || $vatPct < 0) {
            return;
        }

        $den = 1 + ($vatPct / 100);
        if ($den <= 0) {
            return;
        }

        $excl = (int) round($gross / $den, 0);
        $vatAmt = (int) round($gross - $excl, 0);
        $set('line_total', $excl);
        $set('vat_amount_display', $vatAmt);

        $q = self::toFloat($get('quantity'));
        if ($q > 0) {
            $set('unit_price', round($excl / $q, 4));
        }

        $set('line_gross_display', (float) ($excl + $vatAmt));
    }

    /**
     * User adjusted VAT amount (e.g. rounding): keep excl. subtotal, refresh incl. total.
     *
     * @param  callable(string, mixed): void  $set
     * @param  callable(string): mixed  $get
     */
    public static function applyManualVatAmount(callable $set, callable $get): void
    {
        $lt = self::toNullableFloat($get('line_total'));
        $vatAmt = self::toNullableFloat($get('vat_amount_display'));
        if ($lt === null || $vatAmt === null) {
            return;
        }

        $set('line_gross_display', round($lt + $vatAmt, 0));
    }

    private static function toFloat(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return VietnameseMoneyInput::parse($v) ?? 0.0;
    }

    private static function toNullableFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return VietnameseMoneyInput::parse($v);
    }
}
