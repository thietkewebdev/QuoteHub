<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'line_no',
    'quotation_item_id',
    'product_id',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'vat_percent',
    'line_total',
    'notes',
])]
class PurchaseOrderLine extends Model
{
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrderLine $line): void {
            if ($line->line_no === null || $line->line_no === 0) {
                $max = (int) static::query()
                    ->where('purchase_order_id', $line->purchase_order_id)
                    ->max('line_no');
                $line->line_no = $max + 1;
            }

            if ($line->line_total === null && $line->quantity !== null && $line->unit_price !== null) {
                $line->line_total = round((float) $line->quantity * (float) $line->unit_price, 4);
            }
        });

        static::saved(function (PurchaseOrderLine $line): void {
            $line->purchaseOrder?->recalculateTotals();
        });

        static::deleted(function (PurchaseOrderLine $line): void {
            $line->purchaseOrder?->recalculateTotals();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'vat_percent' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }
}
