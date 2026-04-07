<?php

namespace App\Actions\Quotation;

use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Services\Quotation\ManualQuotationPayloadEnricher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveManualQuotationDraftAction
{
    public function __construct(
        private readonly ManualQuotationPayloadEnricher $enricher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Same keys as review form + supplier_id
     */
    public function execute(QuotationReviewDraft $draft, ?User $user, array $payload): QuotationReviewDraft
    {
        if ($draft->ingestion_batch_id !== null) {
            throw new InvalidArgumentException(__('This draft is tied to an ingestion batch; use the batch review flow.'));
        }

        if ($draft->approved_quotation_id !== null) {
            throw new InvalidArgumentException(__('This manual quotation is already approved.'));
        }

        $payload = $this->enricher->enrich($payload);

        return DB::transaction(function () use ($draft, $user, $payload): QuotationReviewDraft {
            $draft->forceFill([
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
                'last_edited_by' => $user?->id,
            ])->save();

            return $draft->refresh();
        });
    }
}
