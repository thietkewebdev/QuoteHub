<?php

namespace App\Services\AI\Contracts;

use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;

/**
 * Pluggable OCR → quotation JSON extraction.
 *
 * @see QuotationExtractionSchema::normalize()
 */
interface QuotationExtractionProviderInterface
{
    /**
     * Stored on {@see AiExtraction::$model_name}.
     */
    public function modelLabel(): string;

    /**
     * @return array<string, mixed> Raw extraction (normalized before persistence)
     */
    public function extract(string $ocrDocumentText, IngestionBatch $batch, ?SupplierExtractionContext $supplierContext = null): array;
}
