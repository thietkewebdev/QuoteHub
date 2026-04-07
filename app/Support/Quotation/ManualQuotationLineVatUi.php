<?php

namespace App\Support\Quotation;

use App\Support\Locale\VietnameseMoneyInput;

/**
 * Keeps line VAT display fields in sync for manual / review quotation repeaters.
 *
 * Persisted semantics: unit_price and line_total are both before VAT (excl.).
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
            $vatAmt = round($lt * $vat / 100, 4);
            $set('vat_amount_display', $vatAmt);
            $set('line_gross_display', round($lt + $vatAmt, 4));
        } else {
            $set('vat_amount_display', null);
            $set('line_gross_display', $lt);
        }
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
