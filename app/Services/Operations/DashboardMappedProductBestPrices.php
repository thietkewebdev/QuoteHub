<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Product;
use App\Services\Quotation\PriceHistoryQuery;
use App\Support\Locale\VietnamesePresentation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        $groups = $this->aggregateGroups($base, $limit);

        return $this->hydrateSpotlightGroups($base, $groups);
    }

    /**
     * Dashboard lookup rows: active catalog products (default: first by name, like the Products list), optionally
     * filtered by name/SKU. Each row includes best visible history unit price when any mapped line exists.
     *
     * Uses case-insensitive matching on PostgreSQL (`ilike`) so search matches Render (pgsql) expectations.
     *
     * @return Collection<int, object{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: ?string,
     *     best_unit_price: ?float,
     *     best_supplier_name: string,
     *     quote_date_label: ?string,
     *     distinct_suppliers: int,
     *     specs_text: ?string,
     * }>
     */
    public function catalogLookupRows(?string $query, int $limit = 25): Collection
    {
        $products = $this->orderedActiveProductsForLookup($query, $limit);

        if ($products->isEmpty()) {
            return collect();
        }

        $ids = $products->pluck('id');
        $base = $this->spotlightBase()
            ->whereIn('quotation_items.mapped_product_id', $ids);

        $groups = $this->aggregateGroupsForProductSet($base);
        $byProductId = $groups->keyBy(fn (object $g): int => (int) $g->product_id);

        return $products->map(function (Product $product) use ($base, $byProductId): object {
            $group = $byProductId->get($product->getKey());
            $row = $group === null
                ? $this->emptyPriceRowForProduct($product)
                : $this->hydrateOneSpotlightGroup($base, $group);
            $row->specs_text = $this->productSpecsForDisplay($product);

            return $row;
        })->values();
    }

    /**
     * @return Collection<int, Product>
     */
    private function orderedActiveProductsForLookup(?string $query, int $limit): Collection
    {
        $trimmed = Str::trim((string) ($query ?? ''));

        $q = Product::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($trimmed !== '') {
            $pattern = '%'.addcslashes($trimmed, '%_\\').'%';
            $op = $this->nameOrSkuLikeOperator();
            $q->where(function (EloquentBuilder $w) use ($pattern, $op): void {
                $w->where('name', $op, $pattern)
                    ->orWhere('sku', $op, $pattern)
                    ->orWhere('specs_text', $op, $pattern);
            });
        }

        return $q->limit($limit)->get();
    }

    /**
     * PostgreSQL LIKE is case-sensitive; use ILIKE so dashboard search matches Render (pgsql) and local (sqlite).
     */
    private function nameOrSkuLikeOperator(): string
    {
        return Product::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function productSpecsForDisplay(Product $product): ?string
    {
        $s = trim((string) ($product->specs_text ?? ''));

        return $s !== '' ? $s : null;
    }

    private function emptyPriceRowForProduct(Product $product): object
    {
        return (object) [
            'product_id' => (int) $product->getKey(),
            'product_name' => (string) $product->name,
            'product_sku' => filled($product->sku) ? (string) $product->sku : null,
            'best_unit_price' => null,
            'best_supplier_name' => '—',
            'quote_date_label' => null,
            'distinct_suppliers' => 0,
        ];
    }

    /**
     * One aggregate row per product that has at least one visible history line (caller constrains product ids).
     *
     * @return Collection<int, object>
     */
    private function aggregateGroupsForProductSet(EloquentBuilder $base): Collection
    {
        return $this->aggregatesOnlyQuery($base)->get();
    }

    private function spotlightBase(): EloquentBuilder
    {
        return PriceHistoryQuery::make()
            ->whereNotNull('quotation_items.mapped_product_id')
            ->join('products', 'products.id', '=', 'quotation_items.mapped_product_id')
            ->where('products.is_active', true);
    }

    private function aggregateGroups(EloquentBuilder $base, int $limit): Collection
    {
        return $this->aggregatesOnlyQuery($base)
            ->orderByDesc('latest_quote_date')
            ->limit($limit)
            ->get();
    }

    /**
     * {@see PriceHistoryQuery::make()} selects quotation_items.* and a group key; PostgreSQL rejects that combined
     * with GROUP BY. Reset the select list to aggregates only before running MIN/COUNT/MAX.
     */
    private function aggregatesOnlyQuery(EloquentBuilder $base): QueryBuilder
    {
        $query = $base->clone()->toBase();
        $query->select([]);
        $query->selectRaw('quotation_items.mapped_product_id as product_id');
        $query->selectRaw('MIN(quotation_items.unit_price) as best_unit_price');
        $query->selectRaw('COUNT(DISTINCT quotations.supplier_name) as distinct_suppliers');
        $query->selectRaw('MAX(quotations.quote_date) as latest_quote_date');
        $query->groupBy('quotation_items.mapped_product_id');

        return $query;
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
    private function hydrateSpotlightGroups(EloquentBuilder $base, Collection $groups): Collection
    {
        return $groups->map(fn (object $g): object => $this->hydrateOneSpotlightGroup($base, $g))->values();
    }

    /**
     * @param  object{
     *     product_id: mixed,
     *     best_unit_price: mixed,
     *     distinct_suppliers: mixed,
     * }  $g
     * @return object{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: ?string,
     *     best_unit_price: float,
     *     best_supplier_name: string,
     *     quote_date_label: ?string,
     *     distinct_suppliers: int,
     * }
     */
    private function hydrateOneSpotlightGroup(EloquentBuilder $base, object $g): object
    {
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
    }
}
