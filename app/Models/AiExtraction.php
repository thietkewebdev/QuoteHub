<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingestion_batch_id',
    'model_name',
    'prompt_version',
    'extraction_json',
    'confidence_overall',
    'warnings',
])]
class AiExtraction extends Model
{
    public function ingestionBatch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class);
    }

    protected function casts(): array
    {
        return [
            'extraction_json' => 'array',
            'confidence_overall' => 'decimal:6',
            'warnings' => 'array',
        ];
    }
}
