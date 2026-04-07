<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'brand_id',
    'product_category_id',
    'sku',
    'name',
    'slug',
    'description',
    'barcode',
    'unit_of_measure',
    'is_active',
    'metadata',
])]
class Product extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function mappedQuotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'mapped_product_id');
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'product_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (! filled($product->name) || filled($product->slug)) {
                return;
            }

            $base = Str::slug($product->name) ?: 'product';
            $slug = $base;
            $n = 0;
            while (static::query()
                ->where('slug', $slug)
                ->when($product->exists, fn ($q) => $q->whereKeyNot($product->getKey()))
                ->exists()) {
                $slug = $base.'-'.(++$n);
            }
            $product->slug = $slug;
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
