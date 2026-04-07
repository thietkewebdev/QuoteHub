<?php

namespace App\Services\OCR;

final readonly class OcrExtractionResult
{
    /**
     * @param  array<string, mixed>|null  $structuredBlocks
     */
    public function __construct(
        public string $engineName,
        public string $rawText,
        public ?float $confidence = null,
        public ?array $structuredBlocks = null,
    ) {}
}
