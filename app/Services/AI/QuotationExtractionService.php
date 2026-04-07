<?php

namespace App\Services\AI;

use App\Models\AiExtraction;
use App\Models\ExtractionAttempt;
use App\Models\IngestionBatch;
use App\Services\AI\Contracts\QuotationExtractionProviderInterface;
use App\Services\AI\Correction\QuotationExtractionAutoCorrector;
use App\Services\AI\Refinement\LineItemTextRefinementService;
use App\Services\AI\SupplierExtraction\SupplierExtractionProfileResolver;
use App\Services\AI\Validation\QuotationExtractionValidator;
use App\Services\OCR\GoogleOcrStructuredDocumentCompiler;
use App\Services\Quotation\HybridExtraction\DraftPayloadBuilder;
use App\Services\Quotation\HybridExtraction\HybridQuotationExtractionPipeline;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationExtractionService
{
    public function __construct(
        protected QuotationExtractionProviderInterface $extractor,
        protected SupplierExtractionProfileResolver $supplierProfileResolver,
        protected QuotationExtractionValidator $extractionValidator,
        protected QuotationExtractionAutoCorrector $autoCorrector,
        protected LineItemTextRefinementService $lineItemTextRefinement,
        protected HybridQuotationExtractionPipeline $hybridPipeline,
        protected DraftPayloadBuilder $draftPayloadBuilder,
    ) {}

    /**
     * Build one OCR document from all non-empty OCR rows for files ordered by page_order, then persist.
     */
    public function extractAndPersist(IngestionBatch $batch): AiExtraction
    {
        $compiled = $this->compileOcrDocument($batch);

        if (trim($compiled) === '') {
            throw new InvalidArgumentException(__('No Google OCR structured content is available for this batch.'));
        }

        $document = $compiled;

        $supplierContext = $this->supplierProfileResolver->resolve($batch, $document);
        $useHybrid = strtolower((string) config('quotation_ai.pipeline.mode', 'llm_table')) === 'hybrid';

        $raw = $useHybrid
            ? $this->hybridPipeline->extract($batch, $document, $supplierContext)
            : $this->extractor->extract($document, $batch, $supplierContext);

        $normalized = QuotationExtractionSchema::normalize($raw);
        $normalized['extraction_meta']['ocr_refinement'] = [
            'applied' => false,
            'disabled' => true,
            'reason' => 'google_structured_ocr_only',
        ];
        if ($useHybrid) {
            $normalized['extraction_meta']['hybrid'] = array_replace_recursive(
                is_array($normalized['extraction_meta']['hybrid'] ?? null) ? $normalized['extraction_meta']['hybrid'] : [],
                ['pipeline_mode' => 'hybrid'],
            );
        }
        $normalized = $this->lineItemTextRefinement->refineIfEnabled($normalized);
        $normalized['extraction_meta']['line_text_refinement'] = $this->lineItemTextRefinement->consumeLastMeta();
        $normalized = $this->autoCorrector->apply($normalized);
        $normalized = $this->extractionValidator->apply($normalized);
        QuotationExtractionSchema::assertValid($normalized);

        $modelLabel = $useHybrid
            ? (string) config('quotation_ai.hybrid.model_label', 'hybrid-v1')
            : $this->extractor->modelLabel();
        $promptVersion = $useHybrid
            ? (string) config('quotation_ai.hybrid.prompt_version', 'hybrid-v1-row-normalizer')
            : (string) config('quotation_ai.prompt_version', 'v2-vietnamese');

        $ai = DB::transaction(function () use ($batch, $normalized, $supplierContext, $modelLabel, $promptVersion): AiExtraction {
            $ai = AiExtraction::query()->updateOrCreate(
                ['ingestion_batch_id' => $batch->id],
                array_merge(
                    [
                        'model_name' => $modelLabel,
                        'prompt_version' => $promptVersion,
                        'extraction_json' => $normalized,
                        'confidence_overall' => $normalized['overall_confidence'],
                        'warnings' => $normalized['document_warnings'],
                    ],
                    $supplierContext->persistencePayload(),
                )
            );

            if ((bool) config('quotation_ai.history.enabled', true)) {
                ExtractionAttempt::query()
                    ->where('ingestion_batch_id', $batch->id)
                    ->update(['is_latest' => false]);

                ExtractionAttempt::query()->create([
                    'ingestion_batch_id' => $batch->id,
                    'ai_extraction_id' => $ai->id,
                    'attempt_number' => ExtractionAttempt::nextAttemptNumber($batch->id),
                    'is_latest' => true,
                    'model_name' => $modelLabel,
                    'prompt_version' => $promptVersion,
                    'result_json' => $normalized,
                    'confidence_overall' => $normalized['overall_confidence'],
                ]);
            }

            return $ai;
        });

        $this->draftPayloadBuilder->persistAfterExtraction($batch->fresh(), $ai);

        return $ai;
    }

    public function compileOcrDocument(IngestionBatch $batch): string
    {
        return GoogleOcrStructuredDocumentCompiler::compileBatchToDocument($batch);
    }
}
