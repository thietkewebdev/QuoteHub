<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id',
    'name',
    'job_title',
    'email',
    'phone',
    'notes',
    'is_primary',
    'sort_order',
])]
class SupplierContact extends Model
{
    protected static function booted(): void
    {
        static::saved(function (SupplierContact $contact): void {
            if (! $contact->is_primary) {
                return;
            }

            static::query()
                ->where('supplier_id', $contact->supplier_id)
                ->whereKeyNot($contact->getKey())
                ->update(['is_primary' => false]);
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
