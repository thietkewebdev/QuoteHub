<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search ranking and lookup helpers for supplier pickers.
 */
final class SupplierMatchingService
{
    public function findByQuotationSupplierName(?string $supplierName): ?Supplier
    {
        if ($supplierName === null || trim($supplierName) === '') {
            return null;
        }

        $normalized = SupplierNameNormalizer::normalize($supplierName);

        return $normalized === ''
            ? null
            : Supplier::query()->where('normalized_name', $normalized)->first();
    }

    /**
     * Order suppliers so the best match to the user's search appears first (exact normalized, then partial).
     */
    public function applySearchRanking(Builder $query, ?string $search): Builder
    {
        $qualifiedNorm = $query->qualifyColumn('normalized_name');
        $qualifiedName = $query->qualifyColumn('name');

        if ($search === null || trim($search) === '') {
            return $query
                ->orderByDesc($query->qualifyColumn('is_active'))
                ->orderBy($qualifiedName);
        }

        $norm = SupplierNameNormalizer::normalize($search);
        $likeNorm = '%'.addcslashes($norm, '%_\\').'%';

        if ($norm === '') {
            return $query
                ->orderByDesc($query->qualifyColumn('is_active'))
                ->orderBy($qualifiedName);
        }

        return $query
            ->orderByRaw(
                "CASE
                    WHEN {$qualifiedNorm} = ? THEN 0
                    WHEN {$qualifiedNorm} LIKE ? ESCAPE '\\\\' THEN 1
                    ELSE 2
                END",
                [$norm, $likeNorm],
            )
            ->orderByDesc($query->qualifyColumn('is_active'))
            ->orderBy($qualifiedName);
    }
}
