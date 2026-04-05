<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingestion_file_id',
    'engine_name',
    'raw_text',
    'structured_blocks',
    'tables_json',
    'confidence',
])]
class OcrResult extends Model
{
    public function ingestionFile(): BelongsTo
    {
        return $this->belongsTo(IngestionFile::class);
    }

    protected function casts(): array
    {
        return [
            'structured_blocks' => 'array',
            'tables_json' => 'array',
            'confidence' => 'decimal:6',
        ];
    }
}
