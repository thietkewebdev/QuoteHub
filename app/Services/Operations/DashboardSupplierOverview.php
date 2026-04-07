<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Quotation\PriceHistoryQuery;
use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Support\Collection;

/**
 * Executive-style supplier snapshot: catalog coverage + price competitiveness vs other suppliers.
 *
 * Competitiveness uses only {@see mapped_product_id} lines (apples-to-apples). A product is “comparable”
 * when ≥2 normalized suppliers have at least one mapped line. A supplier “wins” when their minimum
 * unit price (excl. VAT) on that product equals the global minimum (ties count for each tied supplier).
 */
final class DashboardSupplierOverview
{
    private const PRICE_EPS = 0.0001;

    /**
     * @return Collection<int, object{
     *     row_id: string,
     *     supplier_label: string,
     *     catalog_products_quoted: int,
     *     comparable_products: int,
     *     best_price_wins: int,
     *     best_price_share_pct: float|null,
     *     rating_key: string,
     *     rating_label: string,
     *     rating_color: string,
     *     price_vs_others_label: string|null,
     * }>
     */
    public function executiveLeaderboard(int $limit = 16): Collection
    {
        /** @var array<string, array<int, float>> $minPriceBySupplierProduct norm => [productId => min unit_price] */
        $minPriceBySupplierProduct = [];
        /** @var array<string, string> $displayLabel norm => longest seen display name */
        $displayLabel = [];

        $query = PriceHistoryQuery::make()
            ->whereNotNull('quotation_items.mapped_product_id')
            ->toBase()
            ->select([
                'quotations.supplier_name as supplier_name',
                'quotation_items.mapped_product_id as product_id',
                'quotation_items.unit_price as unit_price',
            ]);

        foreach ($query->cursor() as $row) {
            $rawName = (string) ($row->supplier_name ?? '');
            $norm = SupplierNameNormalizer::normalize($rawName);
            if ($norm === '') {
                $norm = "\0empty";
            }

            $label = trim($rawName) !== '' ? trim($rawName) : __('(no supplier name)');
            if (! isset($displayLabel[$norm]) || mb_strlen($label, 'UTF-8') > mb_strlen($displayLabel[$norm], 'UTF-8')) {
                $displayLabel[$norm] = $label;
            }

            $pid = (int) $row->product_id;
            $price = (float) $row->unit_price;
            if ($price <= 0) {
                continue;
            }

            if (! isset($minPriceBySupplierProduct[$norm][$pid])) {
                $minPriceBySupplierProduct[$norm][$pid] = $price;
            } else {
                $minPriceBySupplierProduct[$norm][$pid] = min($minPriceBySupplierProduct[$norm][$pid], $price);
            }
        }

        /** @var array<int, array<string, float>> $minByProduct productId => [norm => min price] */
        $minByProduct = [];
        foreach ($minPriceBySupplierProduct as $norm => $products) {
            foreach ($products as $pid => $minP) {
                $minByProduct[$pid][$norm] = $minP;
            }
        }

        $out = [];
        foreach ($minPriceBySupplierProduct as $norm => $products) {
            $catalogProductsQuoted = count($products);
            $comparable = 0;
            $wins = 0;

            foreach ($products as $pid => $myMin) {
                $suppliersOnProduct = $minByProduct[$pid] ?? [];
                if (count($suppliersOnProduct) < 2) {
                    continue;
                }
                $comparable++;
                $globalMin = min($suppliersOnProduct);
                if (self::pricesEqual($myMin, $globalMin)) {
                    $wins++;
                }
            }

            $pct = $comparable > 0 ? round(100 * $wins / $comparable, 1) : null;
            $rating = self::ratingFor($comparable, $pct);
            $vsLabel = $comparable > 0 && $pct !== null
                ? __(':wins/:total products at best price (:pct)', [
                    'wins' => $wins,
                    'total' => $comparable,
                    'pct' => number_format((float) $pct, 1, ',', '.').'%',
                ])
                : null;

            $out[] = (object) [
                'row_id' => md5($norm),
                'supplier_label' => $displayLabel[$norm] ?? __('(no supplier name)'),
                'catalog_products_quoted' => $catalogProductsQuoted,
                'comparable_products' => $comparable,
                'best_price_wins' => $wins,
                'best_price_share_pct' => $pct,
                'rating_key' => $rating['key'],
                'rating_label' => $rating['label'],
                'rating_color' => $rating['color'],
                'price_vs_others_label' => $vsLabel,
            ];
        }

        return collect($out)
            ->sort(function (object $a, object $b): int {
                if ($a->comparable_products !== $b->comparable_products) {
                    return $b->comparable_products <=> $a->comparable_products;
                }
                $ap = $a->best_price_share_pct;
                $bp = $b->best_price_share_pct;
                if ($ap === null && $bp === null) {
                    return $b->catalog_products_quoted <=> $a->catalog_products_quoted;
                }
                if ($ap === null) {
                    return 1;
                }
                if ($bp === null) {
                    return -1;
                }
                if (abs($ap - $bp) > 0.05) {
                    return $bp <=> $ap;
                }

                return $b->catalog_products_quoted <=> $a->catalog_products_quoted;
            })
            ->values()
            ->take($limit);
    }

    private static function pricesEqual(float $a, float $b): bool
    {
        return abs($a - $b) < self::PRICE_EPS;
    }

    /**
     * @return array{key: string, label: string, color: string}
     */
    private static function ratingFor(int $comparable, ?float $pct): array
    {
        if ($comparable === 0 || $pct === null) {
            return [
                'key' => 'insufficient',
                'label' => __('Not enough overlap'),
                'color' => 'gray',
            ];
        }

        if ($pct >= 60.0) {
            return [
                'key' => 'strong',
                'label' => __('Strong on price'),
                'color' => 'success',
            ];
        }
        if ($pct >= 35.0) {
            return [
                'key' => 'solid',
                'label' => __('Competitive'),
                'color' => 'primary',
            ];
        }
        if ($pct >= 15.0) {
            return [
                'key' => 'mixed',
                'label' => __('Mixed'),
                'color' => 'warning',
            ];
        }

        return [
            'key' => 'weak',
            'label' => __('Usually higher'),
            'color' => 'danger',
        ];
    }
}
