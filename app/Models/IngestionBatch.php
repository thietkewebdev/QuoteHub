<?php

namespace App\Models;

use App\Services\OCR\GoogleOcrStructuredDocumentCompiler;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'source_channel',
    'supplier_id',
    'received_at',
    'uploaded_by',
    'notes',
    'status',
    'file_count',
    'overall_confidence',
])]
class IngestionBatch extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(IngestionFile::class);
    }

    public function aiExtractions(): HasMany
    {
        return $this->hasMany(AiExtraction::class);
    }

    /**
     * Latest extraction row for this batch (one logical result per batch via updateOrCreate).
     */
    public function aiExtraction(): HasOne
    {
        return $this->hasOne(AiExtraction::class)->latestOfMany();
    }

    public function extractionAttempts(): HasMany
    {
        return $this->hasMany(ExtractionAttempt::class)->orderByDesc('attempt_number');
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    public function quotationReviewDraft(): HasOne
    {
        return $this->hasOne(QuotationReviewDraft::class);
    }

    public function hasOcrResults(): bool
    {
        $rows = OcrResult::query()
            ->whereHas('ingestionFile', fn ($q) => $q->where('ingestion_batch_id', $this->id))
            ->whereIn('engine_name', GoogleOcrStructuredDocumentCompiler::GOOGLE_ENGINES)
            ->get();

        foreach ($rows as $ocr) {
            if (GoogleOcrStructuredDocumentCompiler::ocrResultHasExtractableContent($ocr)) {
                return true;
            }
        }

        return false;
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'file_count' => 'integer',
            'overall_confidence' => 'decimal:6',
        ];
    }
}
