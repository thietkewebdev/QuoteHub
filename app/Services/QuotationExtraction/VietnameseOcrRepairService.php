<?php

namespace App\Services\QuotationExtraction;

use App\Services\Quotation\HybridExtraction\VietnameseTextCleaner;

/**
 * Step 1: deterministic Vietnamese OCR glue / spacing repair (before name/spec split).
 */
final class VietnameseOcrRepairService
{
    public function __construct(
        private readonly VietnameseTextCleaner $textCleaner,
    ) {}

    public function repair(string $text): string
    {
        return $this->textCleaner->clean($text);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $stringFields
     * @return array<string, mixed>
     */
    public function repairItemStringFields(array $item, array $stringFields): array
    {
        foreach ($stringFields as $key) {
            if (isset($item[$key]) && is_string($item[$key])) {
                $item[$key] = $this->repair($item[$key]);
            }
        }

        return $item;
    }
}
