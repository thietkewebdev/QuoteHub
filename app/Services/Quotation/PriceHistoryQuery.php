<?php

namespace App\Services\Quotation;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\Quotation\PriceHistoryGroupKeySql;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base query for the price history / comparison table: approved lines with a stable comparison group key.
 *
 * AI-ingestion quotations are included only while their ingestion batch still exists (after batch delete,
 * DB cascade should remove those quotations; this also hides any orphaned rows). Manual-entry quotations
 * (no batch) always remain.
 */
final class PriceHistoryQuery
{
    public static function make(): Builder
    {
        return QuotationItem::query()
            ->select('quotation_items.*')
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->whereNotNull('quotations.approved_at')
            ->where(function (Builder $q): void {
                $q->where('quotations.entry_source', Quotation::ENTRY_SOURCE_MANUAL)
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('ingestion_batches')
                            ->whereColumn('ingestion_batches.id', 'quotations.ingestion_batch_id');
                    });
            })
            ->selectRaw('('.PriceHistoryGroupKeySql::expression('quotation_items').') as price_history_group_key')
            ->with(['quotation.approver', 'mappedProduct']);
    }
}
