<?php

namespace App\Actions\Quotation;

use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Support\Quotation\QuotationReviewOcrPayloadKeys;
use Illuminate\Support\Facades\DB;

class SaveQuotationReviewDraftAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(IngestionBatch $batch, ?User $user, array $payload): QuotationReviewDraft
    {
        return DB::transaction(function () use ($batch, $user, $payload): QuotationReviewDraft {
            $existing = QuotationReviewDraft::query()->where('ingestion_batch_id', $batch->id)->first();
            $prev = is_array($existing?->payload_json) ? $existing->payload_json : [];
            foreach (QuotationReviewOcrPayloadKeys::PRESERVE_THROUGH_REVIEW_SAVE as $key) {
                if (array_key_exists($key, $prev)) {
                    $payload[$key] = $prev[$key];
                }
            }

            $aiId = $batch->aiExtraction?->id;

            $draft = QuotationReviewDraft::query()->updateOrCreate(
                ['ingestion_batch_id' => $batch->id],
                [
                    'ai_extraction_id' => $aiId,
                    'payload_json' => $payload,
                    'review_status' => QuotationReviewDraft::STATUS_PENDING,
                    'last_edited_by' => $user?->id,
                ]
            );

            if (in_array($batch->status, ['ai_done', 'review_rejected', 'review_corrections_requested'], true)) {
                $batch->forceFill(['status' => 'review_pending'])->save();
            }

            return $draft;
        });
    }
}
