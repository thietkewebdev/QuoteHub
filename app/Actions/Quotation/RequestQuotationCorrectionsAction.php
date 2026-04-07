<?php

namespace App\Actions\Quotation;

use App\Models\IngestionBatch;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Support\Quotation\QuotationTextNormalizer;
use Illuminate\Support\Facades\DB;

class RequestQuotationCorrectionsAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(IngestionBatch $batch, User $user, array $payload, ?string $note = null): void
    {
        DB::transaction(function () use ($batch, $user, $payload, $note): void {
            QuotationReviewDraft::query()->updateOrCreate(
                ['ingestion_batch_id' => $batch->id],
                [
                    'ai_extraction_id' => $batch->aiExtraction?->id,
                    'payload_json' => $payload,
                    'review_status' => QuotationReviewDraft::STATUS_CORRECTIONS_REQUESTED,
                    'reviewer_notes' => QuotationTextNormalizer::nullableSpacing(
                        filled($note) ? $note : (string) ($payload['reviewer_notes'] ?? '')
                    ),
                    'last_edited_by' => $user->id,
                ]
            );

            $batch->forceFill(['status' => 'review_corrections_requested'])->save();
        });
    }
}
