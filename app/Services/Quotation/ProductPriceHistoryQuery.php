<?php

namespace App\Services\Quotation;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approved quotation lines for a canonical {@see Product}, using the same visibility rules as {@see PriceHistoryQuery}.
 */
final class ProductPriceHistoryQuery
{
    public static function forProduct(int $productId): Builder
    {
        return PriceHistoryQuery::make()
            ->where('quotation_items.mapped_product_id', $productId)
            ->orderByDesc('quotations.approved_at')
            ->orderByDesc('quotation_items.id');
    }
}
