<?php

namespace App\Support\Supplier;

use App\Support\Quotation\QuotationTextNormalizer;

/**
 * Canonical string for supplier deduplication and search (Unicode-aware lowercase).
 */
final class SupplierNameNormalizer
{
    public static function normalize(string $name): string
    {
        $name = QuotationTextNormalizer::spacing($name);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);

        return $name === '' ? '' : mb_strtolower($name, 'UTF-8');
    }
}
