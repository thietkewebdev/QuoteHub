<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingestion_batch_id',
    'ai_extraction_id',
    'attempt_number',
    'is_latest',
    'model_name',
    'prompt_version',
    'result_json',
    'confidence_overall',
])]
class ExtractionAttempt extends Model
{
    public function ingestionBatch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class);
    }

    public function aiExtraction(): BelongsTo
    {
        return $this->belongsTo(AiExtraction::class);
    }

    protected function casts(): array
    {
        return [
            'is_latest' => 'boolean',
            'result_json' => 'array',
            'confidence_overall' => 'decimal:6',
        ];
    }

    public static function nextAttemptNumber(int $ingestionBatchId): int
    {
        $max = self::query()->where('ingestion_batch_id', $ingestionBatchId)->max('attempt_number');

        return $max === null ? 1 : ((int) $max) + 1;
    }
}
