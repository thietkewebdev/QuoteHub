<?php

namespace App\Services\QuotationExtraction;

use App\Services\Quotation\HybridExtraction\ProductNameSplitter as HybridProductNameSplitter;

/**
 * Step 2: split display product name vs specs tail (deterministic; config-driven).
 */
final class ProductNameSplitter
{
    public function __construct(
        private readonly HybridProductNameSplitter $hybridSplitter,
    ) {}

    /**
     * @return array{0: string, 1: string} [raw_name, specs_text]
     */
    public function split(string $rawName, string $specsText, ?int $minNameLength = null): array
    {
        return $this->hybridSplitter->split($rawName, $specsText, $minNameLength);
    }
}
