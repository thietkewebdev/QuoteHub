<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Services\AI\Validation\QuotationExtractionValidator;

/**
 * Deterministic numeric checks on hybrid extraction output (delegates to core validator).
 */
final class NumericConsistencyValidator
{
    public function __construct(
        private readonly QuotationExtractionValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function apply(array $normalized): array
    {
        return $this->validator->apply($normalized);
    }
}
