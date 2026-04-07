<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Product;
use App\Services\Quotation\PriceHistoryQuery;
use App\Support\Locale\VietnamesePresentation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rows for the operations dashboard: canonical product + lowest seen unit price (excl. VAT) among visible history lines.
 */
final class DashboardMappedProductBestPrices
{
    /**
     * @return Collection<int, object{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: ?string,
     *     best_unit_price: float,
     *     best_supplier_name: string,
     *     quote_date_label: ?string,
     *     distinct_suppliers: int,
     * }>
     */
    public function recentSpotlight(int $limit = 14): Collection
    {
        $base = $this->spotlightBase();

        $groups = $this->aggregateGroups(clone $base, $limit);

        return $this->hydrateSpotlightGroups($base, $groups);
    }

    /**
     * Products whose catalog name or SKU matches the query, with best visible history unit price per product.
     *
     * @return Collection<int, object{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: ?string,
     *     best_unit_price: float,
     *     best_supplier_name: string,
     *     quote_date_label: ?string,
     *     distinct_suppliers: int,
     * }>
     */
    public function searchByNameOrSku(?string $query, int $limit = 25): Collection
    {
        $trimmed = $query !== null ? trim($query) : '';
        if ($trimmed === '') {
            return collect();
        }

        $escaped = addcslashes($trimmed, '%_\\');
        $like = '%'.$escaped.'%';

        $matchingIds = Product::query()
            ->where('is_active', true)
            ->where(function (Builder $w) use ($like): void {
                $w->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            })
            ->orderBy('name')
            ->limit(250)
            ->pluck('id');

        if ($matchingIds->isEmpty()) {
            return collect();
        }

        $base = $this->spotlightBase()
            ->whereIn('quotation_items.mapped_product_id', $matchingIds);

        $groups = $this->aggregateGroups(clone $base, $limit);

        return $this->hydrateSpotlightGroups($base, $groups);
    }

    private function spotlightBase(): Builder
    {
        return PriceHistoryQuery::make()
            ->whereNotNull('quotation_items.mapped_product_id')
            ->join('products', 'products.id', '=', 'quotation_items.mapped_product_id')
            ->where('products.is_active', true);
    }

    private function aggregateGroups(Builder $base, int $limit): Collection
    {
        return $base
            ->toBase()
            ->selectRaw('quotation_items.mapped_product_id as product_id')
            ->selectRaw('MIN(quotation_items.unit_price) as best_unit_price')
            ->selectRaw('COUNT(DISTINCT quotations.supplier_name) as distinct_suppliers')
            ->selectRaw('MAX(quotations.quote_date) as latest_quote_date')
            ->groupBy('quotation_items.mapped_product_id')
            ->orderByDesc('latest_quote_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, object>  $groups
     * @return Collection<int, object{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: ?string,
     *     best_unit_price: float,
     *     best_supplier_name: string,
     *     quote_date_label: ?string,
     *     distinct_suppliers: int,
     * }>
     */
    private function hydrateSpotlightGroups(Builder $base, Collection $groups): Collection
    {
        return $groups->map(function (object $g) use ($base): object {
            $pick = (clone $base)
                ->where('quotation_items.mapped_product_id', $g->product_id)
                ->where('quotation_items.unit_price', $g->best_unit_price)
                ->orderByDesc('quotations.quote_date')
                ->orderByDesc('quotation_items.id')
                ->select([
                    'products.name as product_name',
                    'products.sku as product_sku',
                    'quotations.supplier_name',
                    'quotations.quote_date',
                ])
                ->first();

            $quoteDate = $pick?->quote_date;
            $quoteLabel = $quoteDate instanceof CarbonInterface
                ? $quoteDate->format(VietnamesePresentation::DATE_FORMAT)
                : null;

            return (object) [
                'product_id' => (int) $g->product_id,
                'product_name' => (string) ($pick?->product_name ?? ''),
                'product_sku' => $pick && $pick->product_sku !== null && $pick->product_sku !== ''
                    ? (string) $pick->product_sku
                    : null,
                'best_unit_price' => (float) $g->best_unit_price,
                'best_supplier_name' => (string) (($pick?->supplier_name) ?? '—'),
                'quote_date_label' => $quoteLabel,
                'distinct_suppliers' => (int) $g->distinct_suppliers,
            ];
        })->values();
    }
}
