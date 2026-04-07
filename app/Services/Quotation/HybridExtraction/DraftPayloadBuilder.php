<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use App\Support\Quotation\QuotationReviewOcrPayloadKeys;

/**
 * Seeds quotation review draft from extraction JSON after hybrid pipeline completes.
 */
final class DraftPayloadBuilder
{
    public function __construct(
        private readonly QuotationReviewPayloadFactory $payloadFactory,
    ) {}

    public function persistAfterExtraction(IngestionBatch $batch, AiExtraction $ai): void
    {
        if (! (bool) config('quotation_ai.review_draft.seed_from_ai_extraction', true)) {
            return;
        }

        $json = $ai->extraction_json;
        if (! is_array($json)) {
            return;
        }

        $payload = $this->payloadFactory->fromExtractionJson($json);

        $existingRow = QuotationReviewDraft::query()->where('ingestion_batch_id', $batch->id)->first();
        $prev = is_array($existingRow?->payload_json) ? $existingRow->payload_json : [];
        foreach (QuotationReviewOcrPayloadKeys::PRESERVE_THROUGH_REVIEW_SAVE as $key) {
            if (array_key_exists($key, $prev)) {
                $payload[$key] = $prev[$key];
            }
        }

        QuotationReviewDraft::query()->updateOrCreate(
            ['ingestion_batch_id' => $batch->id],
            [
                'ai_extraction_id' => $ai->id,
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
            ]
        );
    }
}
