<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Support\Locale\ProductLineSpecsSplitter;

/**
 * Splits long product titles into display name vs specs (deterministic).
 */
final class ProductNameSplitter
{
    public function split(string $rawName, string $specsText, ?int $minNameLength = null): array
    {
        $min = $minNameLength ?? max(40, (int) config('quotation_ai.line_text_refinement.product_specs_split_min_length', 80));
        if (! (bool) config('quotation_ai.line_text_refinement.product_specs_split', true)) {
            return [trim($rawName), trim($specsText)];
        }

        return ProductLineSpecsSplitter::split($rawName, $specsText, $min);
    }
}
