<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ingestion_batch_id',
    'original_name',
    'mime_type',
    'extension',
    'storage_path',
    'checksum_sha256',
    'page_order',
    'file_size',
    'width',
    'height',
    'preprocessing_meta',
])]
class IngestionFile extends Model
{
    public function isRasterImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function supportsInlinePreview(): bool
    {
        $mime = (string) $this->mime_type;

        return $this->isRasterImage() || $mime === 'application/pdf';
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class, 'ingestion_batch_id');
    }

    public function ocrResults(): HasMany
    {
        return $this->hasMany(OcrResult::class);
    }

    protected function casts(): array
    {
        return [
            'page_order' => 'integer',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'preprocessing_meta' => 'array',
        ];
    }
}
