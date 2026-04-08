<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Concerns;

use App\Models\Quotation;
use App\Models\QuotationItem;

/**
 * Financial figures for the quotation detail layout.
 * Subtotal and VAT use stored header fields when set, else sums from lines.
 * Grand total is always subtotal + VAT (never a lone stored total_amount that could be excl. VAT or stale).
 */
trait InteractsWithQuotationDetailLayout
{
    /**
     * @return array{sub: float, vat: float, total: float} total = grand total including VAT (= sub + vat)
     */
    protected function quotationFinancialSummary(Quotation $quotation): array
    {
        $items = $quotation->relationLoaded('items')
            ? $quotation->items
            : $quotation->items()->get();

        $subFromLines = (float) $items->sum(fn (QuotationItem $i): float => (float) ($i->line_total ?? 0));

        $vatFromLines = (float) $items->sum(function (QuotationItem $i): float {
            $lt = (float) ($i->line_total ?? 0);
            $vp = $i->vat_percent;
            if ($vp === null || $vp === '' || ! is_numeric($vp)) {
                return 0.0;
            }

            return round($lt * (float) $vp / 100, 0);
        });

        $sub = $quotation->subtotal_before_tax !== null
            ? (float) $quotation->subtotal_before_tax
            : $subFromLines;

        $vat = $quotation->tax_amount !== null
            ? (float) $quotation->tax_amount
            : $vatFromLines;

        $grandTotal = round($sub + $vat, 4);

        return [
            'sub' => $sub,
            'vat' => $vat,
            'total' => $grandTotal,
        ];
    }
}
