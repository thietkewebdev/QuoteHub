<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingestion_batch_id',
    'ai_extraction_id',
    'payload_json',
    'review_status',
    'reviewer_notes',
    'last_edited_by',
    'approved_quotation_id',
])]
class QuotationReviewDraft extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CORRECTIONS_REQUESTED = 'corrections_requested';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPROVED = 'approved';

    public function ingestionBatch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class);
    }

    public function aiExtraction(): BelongsTo
    {
        return $this->belongsTo(AiExtraction::class);
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function approvedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'approved_quotation_id');
    }

    /**
     * @param  Builder<QuotationReviewDraft>  $query
     * @return Builder<QuotationReviewDraft>
     */
    public function scopeManualEntryDrafts(Builder $query): Builder
    {
        return $query->whereNull('ingestion_batch_id');
    }

    public function isManualEntryDraft(): bool
    {
        return $this->ingestion_batch_id === null;
    }

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
        ];
    }
}
