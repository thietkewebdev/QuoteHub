<?php

namespace App\Services\QuotationExtraction;

use App\Services\Quotation\HybridExtraction\VatInferenceService as HybridVatInferenceService;

/**
 * Step 4: if tax_per_unit / unit_price ≈ 8% or 10%, set vat_percent and before/after tax unit/line totals (deterministic).
 */
final class VatInferenceService
{
    public function __construct(
        private readonly HybridVatInferenceService $hybridVatInference,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function apply(array &$items): void
    {
        $this->hybridVatInference->apply($items);
    }
}
