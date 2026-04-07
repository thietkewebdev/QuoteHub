<?php

namespace App\Services\Quotation;

use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Support\Quotation\QuotationReviewOcrPayloadKeys;
use App\Support\Quotation\QuotationTextNormalizer;
use Carbon\Carbon;

/**
 * Builds the editable review payload from AI extraction_json (immutable) or an existing draft.
 */
class QuotationReviewPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function forBatch(IngestionBatch $batch): array
    {
        $batch->loadMissing(['aiExtraction', 'quotationReviewDraft', 'quotation']);

        $ai = $batch->aiExtraction;
        $draft = $batch->quotationReviewDraft;

        if ($draft !== null && is_array($draft->payload_json)) {
            if ($this->shouldReseedDraftFromAi($draft, $ai)) {
                return $this->persistReseededDraft($batch, $draft, $ai);
            }

            return $draft->payload_json;
        }

        if (! $ai instanceof AiExtraction || ! is_array($ai->extraction_json)) {
            return $this->emptyPayload();
        }

        $payload = $this->fromExtractionJson($ai->extraction_json);

        $existingRow = QuotationReviewDraft::query()->where('ingestion_batch_id', $batch->id)->first();
        $prev = is_array($existingRow?->payload_json) ? $existingRow->payload_json : [];
        $payload = $this->mergeOcrPreserveKeys($payload, $prev);

        QuotationReviewDraft::query()->updateOrCreate(
            ['ingestion_batch_id' => $batch->id],
            [
                'ai_extraction_id' => $ai->id,
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
            ]
        );

        if ($batch->status === 'ai_done') {
            $batch->forceFill(['status' => 'review_pending'])->save();
        }

        return $payload;
    }

    private function shouldReseedDraftFromAi(QuotationReviewDraft $draft, ?AiExtraction $ai): bool
    {
        if (! (bool) config('quotation_ai.review_draft.seed_from_ai_extraction', true)) {
            return false;
        }

        if (! $ai instanceof AiExtraction || ! is_array($ai->extraction_json)) {
            return false;
        }

        $draftAiId = $draft->ai_extraction_id;

        return $draftAiId === null
            || (int) $draftAiId !== (int) $ai->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function persistReseededDraft(IngestionBatch $batch, QuotationReviewDraft $draft, AiExtraction $ai): array
    {
        $payload = $this->fromExtractionJson($ai->extraction_json);
        $payload = $this->mergeOcrPreserveKeys($payload, $draft->payload_json);

        QuotationReviewDraft::query()->updateOrCreate(
            ['ingestion_batch_id' => $batch->id],
            [
                'ai_extraction_id' => $ai->id,
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
            ]
        );

        if ($batch->status === 'ai_done') {
            $batch->forceFill(['status' => 'review_pending'])->save();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $prev
     * @return array<string, mixed>
     */
    private function mergeOcrPreserveKeys(array $payload, array $prev): array
    {
        foreach (QuotationReviewOcrPayloadKeys::PRESERVE_THROUGH_REVIEW_SAVE as $key) {
            if (array_key_exists($key, $prev)) {
                $payload[$key] = $prev[$key];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $extractionJson
     * @return array<string, mixed>
     */
    public function fromExtractionJson(array $extractionJson): array
    {
        $h = is_array($extractionJson['quotation_header'] ?? null)
            ? $extractionJson['quotation_header']
            : [];

        $quoteDate = $h['quote_date'] ?? '';
        if (is_string($quoteDate) && trim($quoteDate) !== '') {
            try {
                $quoteDate = Carbon::parse($quoteDate)->format('Y-m-d');
            } catch (\Throwable) {
                // keep string for form to show
            }
        } else {
            $quoteDate = null;
        }

        $itemsIn = is_array($extractionJson['items'] ?? null) ? $extractionJson['items'] : [];
        $items = [];
        foreach (array_values($itemsIn) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'raw_name' => QuotationTextNormalizer::spacing((string) ($row['raw_name'] ?? '')),
                'raw_model' => QuotationTextNormalizer::spacing((string) ($row['raw_model'] ?? '')),
                'brand' => QuotationTextNormalizer::spacing((string) ($row['brand'] ?? '')),
                'unit' => QuotationTextNormalizer::spacing((string) ($row['unit'] ?? '')),
                'quantity' => $row['quantity'] ?? null,
                'unit_price' => $row['unit_price'] ?? null,
                'vat_percent' => $row['vat_percent'] ?? null,
                'line_total' => $row['line_total'] ?? null,
                'specs_text' => QuotationTextNormalizer::nullableSpacing((string) ($row['specs_text'] ?? '')) ?? '',
                'mapped_product_id' => null,
            ];
        }

        return [
            'supplier_id' => null,
            'supplier_name' => QuotationTextNormalizer::spacing((string) ($h['supplier_name'] ?? '')),
            'supplier_quote_number' => QuotationTextNormalizer::spacing((string) ($h['supplier_quote_number'] ?? '')),
            'quote_date' => $quoteDate,
            'contact_person' => QuotationTextNormalizer::spacing((string) ($h['contact_person'] ?? '')),
            'notes' => QuotationTextNormalizer::nullableSpacing((string) ($h['notes'] ?? '')) ?? '',
            'total_amount' => $h['total_amount'] ?? null,
            'reviewer_notes' => '',
            'cloned_from_quotation_id' => null,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(): array
    {
        return [
            'supplier_id' => null,
            'supplier_name' => '',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => '',
            'total_amount' => null,
            'reviewer_notes' => '',
            'cloned_from_quotation_id' => null,
            'items' => [],
        ];
    }
}
