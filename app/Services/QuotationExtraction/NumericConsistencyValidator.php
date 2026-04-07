<?php

namespace App\Services\QuotationExtraction;

use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\Validation\QuotationExtractionValidator;

/**
 * Step 5: deterministic numeric consistency checks (no LLM); nudges confidence / warnings only.
 */
final class NumericConsistencyValidator
{
    public function __construct(
        private readonly QuotationExtractionValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized  extraction JSON after {@see QuotationExtractionSchema::normalize}
     * @return array<string, mixed>
     */
    public function apply(array $normalized): array
    {
        return $this->validator->apply($normalized);
    }
}
