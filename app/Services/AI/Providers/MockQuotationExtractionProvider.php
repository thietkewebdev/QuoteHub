<?php

namespace App\Services\AI\Providers;

use App\Models\IngestionBatch;
use App\Services\AI\Contracts\QuotationExtractionProviderInterface;
use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;

/**
 * No LLM: returns an empty template for pipeline tests and environments without OpenAI.
 * Does not invent supplier lines, quote numbers, or line items.
 */
class MockQuotationExtractionProvider implements QuotationExtractionProviderInterface
{
    public function modelLabel(): string
    {
        return 'mock-quotation-extraction';
    }

    public function extract(string $ocrDocumentText, IngestionBatch $batch, ?SupplierExtractionContext $supplierContext = null): array
    {
        $payload = QuotationExtractionSchema::template();
        $payload['quotation_header']['currency'] = 'VND';
        $profileNote = $supplierContext === null
            ? 'supplier profile context not passed.'
            : 'supplier profile mode '.$supplierContext->mode->value.'.';
        $payload['quotation_header']['notes'] = sprintf(
            'Mock driver: no model inference. OCR length %d characters (batch #%s). %s',
            mb_strlen($ocrDocumentText),
            $batch->getKey(),
            $profileNote
        );
        $payload['items'] = [];
        $payload['document_warnings'] = [
            'QUOTATION_AI_DRIVER=mock: extraction_json is not produced from OCR by a language model. Set QUOTATION_AI_DRIVER=openai and OPENAI_API_KEY for real extraction.',
        ];
        $payload['overall_confidence'] = 0.0;

        $engine = strtolower((string) config('quotation_ai.extraction_engine.version', 'v2'));
        $payload['extraction_meta'] = [
            'engine_version' => $engine === 'v1' ? 'v1-single-pass-mock' : 'v2-two-pass-mock',
            'pass_count' => $engine === 'v1' ? 1 : 2,
        ];

        return $payload;
    }
}
