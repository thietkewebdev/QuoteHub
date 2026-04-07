<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * One candidate product table row from OCR / PDF text (before LLM normalization).
 */
final readonly class RawTableRow
{
    /**
     * @param  list<string>  $cells
     */
    public function __construct(
        public int $lineIndex,
        public string $rawLine,
        public array $cells,
    ) {}
}
