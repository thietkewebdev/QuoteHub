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
            ->tap(fn (Builder $q): Builder => self::whereJoinedQuotationVisibleForPriceHistory($q));
    }

    /**
     * Same visibility as {@see make()} for any query that already joins or scopes the `quotations` table.
     */
    public static function whereJoinedQuotationVisibleForPriceHistory(Builder $itemsQuery): Builder
    {
        return $itemsQuery
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

    /**
     * Approved quotations counted in price history (manual, or AI with an existing ingestion batch row).
     * Use inside `whereHas('quotation', …)` on {@see QuotationItem} queries.
     */
    public static function whereQuotationVisibleForPriceHistory(Builder $quotationQuery): Builder
    {
        return $quotationQuery
            ->whereNotNull('approved_at')
            ->where(function (Builder $q) use ($quotationQuery): void {
                $q->where($quotationQuery->qualifyColumn('entry_source'), Quotation::ENTRY_SOURCE_MANUAL)
                    ->orWhereExists(function ($sub) use ($quotationQuery): void {
                        $sub->selectRaw('1')
                            ->from('ingestion_batches')
                            ->whereColumn('ingestion_batches.id', $quotationQuery->qualifyColumn('ingestion_batch_id'));
                    });
            });
    }

    /**
     * Scalar subquery for each `products` row: MIN(unit_price) over mapped lines whose quotation counts for price history.
     * Safe for PostgreSQL in both SELECT lists and ORDER BY (wrap `toSql()` in parentheses and pass `getBindings()`).
     */
    public static function lowestVisibleUnitPricePerProductSubquery(): Builder
    {
        return QuotationItem::query()
            ->selectRaw('MIN(quotation_items.unit_price)')
            ->whereColumn('quotation_items.mapped_product_id', 'products.id')
            ->whereHas('quotation', fn (Builder $q): Builder => self::whereQuotationVisibleForPriceHistory($q));
    }
}
