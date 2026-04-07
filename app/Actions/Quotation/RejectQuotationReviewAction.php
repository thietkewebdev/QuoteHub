<?php

namespace App\Actions\Quotation;

use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Support\Quotation\QuotationTextNormalizer;
use Illuminate\Support\Facades\DB;

class RejectQuotationReviewAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(IngestionBatch $batch, User $user, array $payload, ?string $reason = null): void
    {
        DB::transaction(function () use ($batch, $user, $payload, $reason): void {
            QuotationReviewDraft::query()->updateOrCreate(
                ['ingestion_batch_id' => $batch->id],
                [
                    'ai_extraction_id' => $batch->aiExtraction?->id,
                    'payload_json' => $payload,
                    'review_status' => QuotationReviewDraft::STATUS_REJECTED,
                    'reviewer_notes' => QuotationTextNormalizer::nullableSpacing(
                        filled($reason) ? $reason : (string) ($payload['reviewer_notes'] ?? '')
                    ),
                    'last_edited_by' => $user->id,
                ]
            );

            $batch->forceFill(['status' => 'review_rejected'])->save();
        });
    }
}
