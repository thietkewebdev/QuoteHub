<?php

namespace App\Services\Quotation;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\QuotationItem;
use Illuminate\Support\Collection;

/**
 * Read-only suggestions for staff to map a quotation line to a product.
 *
 * Ranking (higher score wins; per product we keep the best score and merge reasons):
 * 1. Score 100 — exact match: normalized {@code raw_model} equals normalized product {@code sku} (active products only).
 * 2. Score 88 — alias equals normalized {@code raw_model} (active product).
 * 3. Score 82 — alias equals normalized full {@code raw_name} (active product).
 * 4. Score 40–75 — same brand (brand name matches line {@code brand}, case-insensitive) and token overlap
 *    between {@code raw_name} and product {@code name}: {@code 40 + round(35 * jaccard(tokens))}.
 *
 * Jaccard = |A∩B| / |A∪B| on tokens (letters/digits, length ≥ 2). Ties broken by higher score only; list is sorted
 * by score desc, then product name.
 */
final class ProductMappingSuggestionService
{
    /**
     * @return Collection<int, ProductMappingSuggestion>
     */
    public function suggest(QuotationItem $item): Collection
    {
        $byProduct = [];

        $normModel = self::normalize($item->raw_model);
        $normName = self::normalize($item->raw_name);
        $normBrand = self::normalize($item->brand);

        $this->scoreSkuExact($normModel, $byProduct);
        $this->scoreAliasMatches($normModel, $normName, $byProduct);
        $this->scoreBrandTokenOverlap($item, $normBrand, $normName, $byProduct);

        return collect($byProduct)
            ->map(fn (array $row): ProductMappingSuggestion => new ProductMappingSuggestion(
                productId: $row['product_id'],
                score: $row['score'],
                reasons: array_values(array_unique($row['reasons'])),
                productName: $row['name'] ?? null,
                productSku: $row['sku'] ?? null,
            ))
            ->sort(function (ProductMappingSuggestion $a, ProductMappingSuggestion $b): int {
                if ($a->score !== $b->score) {
                    return $b->score <=> $a->score;
                }

                return strcmp(mb_strtolower((string) ($a->productName ?? '')), mb_strtolower((string) ($b->productName ?? '')));
            })
            ->values();
    }

    /**
     * @param  array<int, array{product_id: int, score: int, reasons: list<string>, name?: string, sku?: string}>  $byProduct
     */
    private function scoreSkuExact(string $normModel, array &$byProduct): void
    {
        if ($normModel === '') {
            return;
        }

        $products = Product::query()
            ->select(['id', 'name', 'sku'])
            ->where('is_active', true)
            // Bind empty string for COALESCE so PostgreSQL never sees `""` (identifier) typos in raw SQL.
            ->whereRaw('LOWER(TRIM(COALESCE(sku, ?))) = ?', ['', $normModel])
            ->get();

        foreach ($products as $product) {
            $this->bump(
                $byProduct,
                (int) $product->id,
                100,
                __('Exact SKU / model match'),
                (string) $product->name,
                $product->sku !== null ? (string) $product->sku : null
            );
        }
    }

    /**
     * @param  array<int, array{product_id: int, score: int, reasons: list<string>, name?: string, sku?: string}>  $byProduct
     */
    private function scoreAliasMatches(string $normModel, string $normName, array &$byProduct): void
    {
        if ($normModel === '' && $normName === '') {
            return;
        }

        $q = ProductAlias::query()
            ->select(['product_aliases.alias', 'product_aliases.product_id', 'products.name', 'products.sku'])
            ->join('products', 'products.id', '=', 'product_aliases.product_id')
            ->where('products.is_active', true);

        $q->where(function ($query) use ($normModel, $normName): void {
            if ($normModel !== '') {
                $query->orWhereRaw('LOWER(TRIM(product_aliases.alias)) = ?', [$normModel]);
            }
            if ($normName !== '') {
                $query->orWhereRaw('LOWER(TRIM(product_aliases.alias)) = ?', [$normName]);
            }
        });

        foreach ($q->cursor() as $row) {
            $aliasNorm = self::normalize((string) $row->alias);
            $pid = (int) $row->product_id;

            if ($normModel !== '' && $aliasNorm === $normModel) {
                $this->bump(
                    $byProduct,
                    $pid,
                    88,
                    __('Alias matches model text'),
                    (string) $row->name,
                    $row->sku !== null ? (string) $row->sku : null
                );
            }
            if ($normName !== '' && $aliasNorm === $normName) {
                $this->bump(
                    $byProduct,
                    $pid,
                    82,
                    __('Alias matches product name text'),
                    (string) $row->name,
                    $row->sku !== null ? (string) $row->sku : null
                );
            }
        }
    }

    /**
     * @param  array<int, array{product_id: int, score: int, reasons: list<string>, name?: string, sku?: string}>  $byProduct
     */
    private function scoreBrandTokenOverlap(QuotationItem $item, string $normBrand, string $normName, array &$byProduct): void
    {
        if ($normBrand === '' || $normName === '') {
            return;
        }

        $brand = Brand::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normBrand])
            ->first();

        if ($brand === null) {
            return;
        }

        $nameTokens = self::tokens((string) $item->raw_name);
        if ($nameTokens === []) {
            return;
        }

        $products = Product::query()
            ->select(['id', 'name', 'sku'])
            ->where('is_active', true)
            ->where('brand_id', $brand->id)
            ->limit(200)
            ->get();

        foreach ($products as $product) {
            $pTokens = self::tokens((string) $product->name);
            if ($pTokens === []) {
                continue;
            }
            $j = self::jaccard($nameTokens, $pTokens);
            if ($j <= 0) {
                continue;
            }
            $score = 40 + (int) round(35 * $j);
            $this->bump(
                $byProduct,
                (int) $product->id,
                $score,
                __('Brand match + name token overlap (:percent%)', ['percent' => (int) round(100 * $j)]),
                (string) $product->name,
                $product->sku !== null ? (string) $product->sku : null
            );
        }
    }

    /**
     * @param  array<int, array{product_id: int, score: int, reasons: list<string>, name?: string, sku?: string}>  $byProduct
     */
    private function bump(
        array &$byProduct,
        int $productId,
        int $score,
        string $reason,
        string $name,
        ?string $sku
    ): void {
        if (! isset($byProduct[$productId])) {
            $byProduct[$productId] = [
                'product_id' => $productId,
                'score' => $score,
                'reasons' => [$reason],
                'name' => $name,
                'sku' => $sku,
            ];

            return;
        }

        if ($score > $byProduct[$productId]['score']) {
            $byProduct[$productId]['score'] = $score;
        }
        $byProduct[$productId]['reasons'][] = $reason;
        $byProduct[$productId]['name'] = $byProduct[$productId]['name'] ?? $name;
        $byProduct[$productId]['sku'] = $byProduct[$productId]['sku'] ?? $sku;
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $value): array
    {
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $parts = array_filter(
            explode(' ', mb_strtolower(trim($value))),
            fn (string $t): bool => mb_strlen($t) >= 2
        );

        return array_values(array_unique($parts));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $inter = count(array_intersect($a, $b));
        if ($inter === 0) {
            return 0.0;
        }
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $inter / $union : 0.0;
    }
}
