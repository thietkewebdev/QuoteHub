<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ingestion_batch_id',
    'supplier_extraction_profile_id',
    'supplier_profile_mode',
    'supplier_profile_inference',
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

    public function supplierExtractionProfile(): BelongsTo
    {
        return $this->belongsTo(SupplierExtractionProfile::class);
    }

    public function extractionAttempts(): HasMany
    {
        return $this->hasMany(ExtractionAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'extraction_json' => 'array',
            'confidence_overall' => 'decimal:6',
            'warnings' => 'array',
            'supplier_profile_inference' => 'array',
        ];
    }
}
