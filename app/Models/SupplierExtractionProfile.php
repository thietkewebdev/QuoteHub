<?php

namespace App\Models;

use App\Support\SupplierExtraction\SupplierProfileHintsBag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id',
    'is_enabled',
    'hints',
])]
class SupplierExtractionProfile extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function aiExtractions(): HasMany
    {
        return $this->hasMany(AiExtraction::class, 'supplier_extraction_profile_id');
    }

    public function hintsBag(): SupplierProfileHintsBag
    {
        return SupplierProfileHintsBag::from(is_array($this->hints) ? $this->hints : null);
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'hints' => 'array',
        ];
    }
}
