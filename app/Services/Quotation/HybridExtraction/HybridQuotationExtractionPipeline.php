<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Models\IngestionBatch;
use App\Models\OcrResult;
use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\QuotationExtractionService;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;

/**
 * Hybrid extraction stages (orchestrated here; deterministic VAT + validation continue in {@see QuotationExtractionService}):
 * 1) {@see FileTypeDetector} per ingestion file OCR metadata
 * 2) {@see OcrLayoutExtractor} + {@see TableRowAssembler} — raw rows/cells from compiled OCR text
 * 3) {@see LlmRowNormalizer} — LLM maps rows to structured items (disabled → heuristic when driver=mock)
 * 4) {@see VietnameseTextCleaner} + {@see ProductNameSplitter}
 * 5) {@see VatInferenceService} — 8%/10% per-unit tax ratio normalization
 * 6) {@see HeaderTotalsCalculator} + {@see ConfidenceScorer}
 *
 * After persist: {@see DraftPayloadBuilder} seeds review draft when hybrid is enabled.
 */
final class HybridQuotationExtractionPipeline
{
    public function __construct(
        private readonly FileTypeDetector $fileTypeDetector,
        private readonly OcrLayoutExtractor $ocrLayoutExtractor,
        private readonly TableRowAssembler $tableRowAssembler,
        private readonly LlmRowNormalizer $llmRowNormalizer,
        private readonly VietnameseTextCleaner $vietnameseTextCleaner,
        private readonly ProductNameSplitter $productNameSplitter,
        private readonly VatInferenceService $vatInferenceService,
        private readonly HeaderTotalsCalculator $headerTotalsCalculator,
        private readonly ConfidenceScorer $confidenceScorer,
    ) {}

    /**
     * @return array<string, mixed> raw extraction (same shape as LLM table provider, before {@see QuotationExtractionSchema::normalize})
     */
    public function extract(
        IngestionBatch $batch,
        string $compiledOcrDocument,
        ?SupplierExtractionContext $supplierContext = null,
    ): array {
        $batch->loadMissing(['files']);

        $fileTypes = [];
        foreach ($batch->files()->orderBy('page_order')->orderBy('id')->get() as $file) {
            /** @var OcrResult|null $ocr */
            $ocr = $file->ocrResults()->orderByDesc('id')->first();
            $fileTypes[] = [
                'ingestion_file_id' => $file->id,
                'original_name' => $file->original_name,
                'detected_type' => $this->fileTypeDetector->detectFromOcrResult($ocr, (string) $file->mime_type),
            ];
        }

        $lines = $this->ocrLayoutExtractor->linesFromCompiledDocument($compiledOcrDocument);
        $rawRows = $this->tableRowAssembler->assemble($lines);

        $llm = $this->llmRowNormalizer->normalize($compiledOcrDocument, $batch, $rawRows, $supplierContext);

        $header = is_array($llm['quotation_header'] ?? null) ? $llm['quotation_header'] : [];
        $items = is_array($llm['items'] ?? null) ? array_values($llm['items']) : [];
        $llmDocWarnings = is_array($llm['llm_document_warnings'] ?? null)
            ? $llm['llm_document_warnings']
            : [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['raw_name', 'raw_model', 'brand', 'unit', 'warranty_text', 'origin_text', 'specs_text'] as $tf) {
                if (isset($item[$tf]) && is_string($item[$tf])) {
                    $items[$i][$tf] = $this->vietnameseTextCleaner->clean($item[$tf]);
                }
            }
            $name = (string) ($items[$i]['raw_name'] ?? '');
            $specs = (string) ($items[$i]['specs_text'] ?? '');
            [$name2, $specs2] = $this->productNameSplitter->split($name, $specs);
            $items[$i]['raw_name'] = $name2;
            $items[$i]['specs_text'] = $specs2;
        }

        $this->vatInferenceService->apply($items);

        [$header, $aggWarnings] = $this->headerTotalsCalculator->merge($header, $items);

        $overall = $this->confidenceScorer->overall($items, (float) config('quotation_ai.hybrid.base_confidence', 0.72));

        $documentWarnings = array_values(array_unique(array_merge(
            array_map(fn ($w) => (string) $w, $llmDocWarnings),
            array_map(fn ($w) => (string) $w, $aggWarnings),
        )));

        return [
            'quotation_header' => $header,
            'items' => $items,
            'document_warnings' => $documentWarnings,
            'overall_confidence' => $overall,
            'extraction_meta' => [
                'engine_version' => 'hybrid-v1',
                'pass_count' => 1,
                'hybrid' => [
                    'file_types' => $fileTypes,
                    'line_count' => count($lines),
                    'raw_row_count' => count($rawRows),
                ],
            ],
        ];
    }
}
