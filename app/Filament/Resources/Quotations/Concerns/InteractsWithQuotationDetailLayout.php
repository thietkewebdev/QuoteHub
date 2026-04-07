<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Concerns;

use App\Models\Quotation;
use App\Models\QuotationItem;

/**
 * Financial figures for the quotation detail layout (header uses stored header fields when set, else sums lines).
 */
trait InteractsWithQuotationDetailLayout
{
    /**
     * @return array{sub: float, vat: float, total: float}
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

        $total = $quotation->total_amount !== null
            ? (float) $quotation->total_amount
            : ($sub + $vat);

        return [
            'sub' => $sub,
            'vat' => $vat,
            'total' => $total,
        ];
    }
}
