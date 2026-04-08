<?php

namespace App\Services\Quotation;

use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Support\Locale\VietnameseMoneyInput;
use App\Support\Quotation\ManualQuotationLineVatUi;
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
                return $this->withVietnameseMoneyDisplayForForm($this->persistReseededDraft($batch, $draft, $ai));
            }

            return $this->withVietnameseMoneyDisplayForForm($draft->payload_json);
        }

        if (! $ai instanceof AiExtraction || ! is_array($ai->extraction_json)) {
            return $this->withVietnameseMoneyDisplayForForm($this->emptyPayload());
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

        return $this->withVietnameseMoneyDisplayForForm($payload);
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
     * Filament fill() uses raw state; review line fields in "manual adjust" mode do not run sync for unit_price/line_total.
     * Format amounts here so the review form shows Vietnamese grouping (.) immediately after AI seed or when loading a draft.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withVietnameseMoneyDisplayForForm(array $payload): array
    {
        if (array_key_exists('total_amount', $payload) && $payload['total_amount'] !== null && $payload['total_amount'] !== '') {
            $formatted = VietnameseMoneyInput::formatForDisplay($payload['total_amount']);
            if ($formatted !== null) {
                $payload['total_amount'] = $formatted;
            }
        }

        if (! isset($payload['items']) || ! is_array($payload['items'])) {
            return $payload;
        }

        foreach ($payload['items'] as $i => $item) {
            if (! is_array($payload['items'][$i])) {
                continue;
            }
            $this->ensureLineSubtotalFromQtyUnitPrice($payload['items'][$i]);
        }

        foreach ($payload['items'] as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (['unit_price', 'line_total', 'vat_amount_display', 'line_gross_display'] as $key) {
                if (! array_key_exists($key, $payload['items'][$i])) {
                    continue;
                }
                $v = $payload['items'][$i][$key];
                if ($v === null || $v === '') {
                    continue;
                }
                $formatted = VietnameseMoneyInput::formatForDisplay($v);
                if ($formatted !== null) {
                    $payload['items'][$i][$key] = $formatted;
                }
            }
        }

        // AI payload often omits vat_amount_display & line_gross_display; recompute like the repeater sync + setMoney().
        foreach ($payload['items'] as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $bag = $payload['items'][$i];
            $set = function (string $key, mixed $value) use (&$bag): void {
                $bag[$key] = $value;
            };
            $get = fn (string $key): mixed => $bag[$key] ?? null;
            ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: false);
            $payload['items'][$i] = $bag;
        }

        return $payload;
    }

    /**
     * When AI omits line subtotal (excl. VAT), derive it so VAT / gross display can sync.
     *
     * @param  array<string, mixed>  $row
     */
    private function ensureLineSubtotalFromQtyUnitPrice(array &$row): void
    {
        $lt = $row['line_total'] ?? null;
        if ($lt !== null && $lt !== '') {
            return;
        }

        $qRaw = $row['quantity'] ?? null;
        $q = is_numeric($qRaw) ? (float) $qRaw : (VietnameseMoneyInput::parse($qRaw) ?? 0.0);
        $p = VietnameseMoneyInput::parse($row['unit_price'] ?? null);
        if ($q > 0 && $p !== null && $p > 0) {
            $row['line_total'] = round($q * $p, 4);
        }
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
