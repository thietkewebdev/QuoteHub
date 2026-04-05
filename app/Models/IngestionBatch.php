<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'file_count' => 'integer',
            'overall_confidence' => 'decimal:6',
        ];
    }
}
