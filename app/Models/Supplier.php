<?php

namespace App\Models;

use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'code',
    'email',
    'phone',
    'website',
    'is_active',
    'metadata',
])]
class Supplier extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $normalized = SupplierNameNormalizer::normalize((string) $supplier->name);
            $supplier->normalized_name = $normalized !== '' ? $normalized : null;

            if ($supplier->code !== null && trim((string) $supplier->code) === '') {
                $supplier->code = null;
            }
        });
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function ingestionBatches(): HasMany
    {
        return $this->hasMany(IngestionBatch::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->orderByDesc('order_date')->orderByDesc('id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderBy('sort_order')->orderBy('id');
    }

    public function extractionProfile(): HasOne
    {
        return $this->hasOne(SupplierExtractionProfile::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
