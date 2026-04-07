<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Support\Quotation\QuotationTextNormalizer;
use App\Support\Supplier\SupplierNameNormalizer;
use InvalidArgumentException;

/**
 * Creates and resolves supplier master rows using normalized names (deduplication).
 */
final class SupplierRegistryService
{
    public function findByNormalizedName(string $displayName): ?Supplier
    {
        $normalized = SupplierNameNormalizer::normalize($displayName);

        if ($normalized === '') {
            return null;
        }

        return Supplier::query()->where('normalized_name', $normalized)->first();
    }

    /**
     * Returns an existing supplier when the normalized name matches; otherwise creates one.
     * Does not mutate approved quotation rows.
     */
    public function findOrCreateByDisplayName(string $displayName, ?string $code = null): Supplier
    {
        $name = QuotationTextNormalizer::spacing($displayName);
        $normalized = SupplierNameNormalizer::normalize($name);
        if ($normalized === '') {
            throw new InvalidArgumentException(__('Supplier name is required.'));
        }

        $existing = Supplier::query()->where('normalized_name', $normalized)->first();
        if ($existing !== null) {
            return $existing;
        }

        return Supplier::query()->create([
            'name' => $name,
            'code' => filled($code) ? QuotationTextNormalizer::spacing($code) : null,
            'is_active' => true,
        ]);
    }
}
