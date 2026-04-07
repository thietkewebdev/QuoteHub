<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'po_number',
    'supplier_id',
    'quotation_id',
    'status',
    'order_date',
    'expected_delivery_date',
    'currency',
    'notes',
    'subtotal_before_tax',
    'tax_amount',
    'total_amount',
    'created_by',
])]
class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_ISSUED => __('Issued'),
            self::STATUS_CANCELLED => __('Cancelled'),
            self::STATUS_COMPLETED => __('Completed'),
        ];
    }

    public static function nextPoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';

        $last = static::query()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $seq = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches)) {
            $seq = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order): void {
            if (! filled($order->po_number)) {
                $order->po_number = static::nextPoNumber();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function recalculateTotals(): void
    {
        $this->unsetRelation('lines');
        $this->load('lines');

        $subtotal = $this->lines->sum(fn (PurchaseOrderLine $line): float => (float) ($line->line_total ?? 0));

        $tax = $this->lines->sum(function (PurchaseOrderLine $line): float {
            $base = (float) ($line->line_total ?? 0);
            $vat = (float) ($line->vat_percent ?? 0);

            return round($base * $vat / 100, 4);
        });

        $this->forceFill([
            'subtotal_before_tax' => round($subtotal, 4),
            'tax_amount' => round($tax, 4),
            'total_amount' => round($subtotal + $tax, 4),
        ])->saveQuietly();
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'subtotal_before_tax' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
    }
}
