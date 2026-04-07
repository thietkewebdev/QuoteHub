<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quotation_id',
    'line_no',
    'raw_name',
    'raw_name_raw',
    'raw_model',
    'brand',
    'unit',
    'quantity',
    'unit_price',
    'vat_percent',
    'line_total',
    'specs_text',
    'line_snapshot_json',
    'mapped_product_id',
    'mapped_at',
    'mapped_by',
])]
class QuotationItem extends Model
{
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function mappedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mapped_product_id');
    }

    public function mappedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function productMappingAudits(): HasMany
    {
        return $this->hasMany(QuotationItemProductMappingAudit::class)->orderByDesc('created_at');
    }

    /**
     * Human-readable line label for tables (mapped catalog name, else raw name/model).
     */
    public function displayLabel(): string
    {
        $mapped = $this->mappedProduct;
        if ($mapped !== null && filled($mapped->name)) {
            return (string) $mapped->name;
        }

        $raw = trim((string) ($this->raw_name ?? ''));
        if ($raw !== '') {
            return $raw;
        }

        $model = trim((string) ($this->raw_model ?? ''));

        return $model !== '' ? $model : '—';
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'vat_percent' => 'decimal:4',
            'line_total' => 'decimal:4',
            'line_snapshot_json' => 'array',
            'mapped_at' => 'datetime',
        ];
    }
}
