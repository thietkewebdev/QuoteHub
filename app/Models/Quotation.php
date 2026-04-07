<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ingestion_batch_id',
    'ai_extraction_id',
    'supplier_id',
    'supplier_name',
    'supplier_quote_number',
    'quote_date',
    'contact_person',
    'notes',
    'pricing_policy',
    'valid_until',
    'currency',
    'subtotal_before_tax',
    'tax_amount',
    'total_amount',
    'header_snapshot_json',
    'entry_source',
    'approved_by',
    'approved_at',
])]
class Quotation extends Model
{
    public const ENTRY_SOURCE_AI_INGESTION = 'ai_ingestion';

    public const ENTRY_SOURCE_MANUAL = 'manual_entry';

    public const PRICING_POLICY_STANDARD = 'standard';

    public const PRICING_POLICY_REFERENCE_ONLY = 'reference_only';

    public const PRICING_POLICY_CONFIRMED_WITH_SUPPLIER = 'confirmed_with_supplier';

    public const PRICING_POLICY_INTERNAL_ONLY = 'internal_only';

    public const PRICING_POLICY_VOID = 'void';

    /**
     * @return array<string, string>
     */
    public static function pricingPolicyOptions(): array
    {
        return [
            self::PRICING_POLICY_STANDARD => __('Standard (operational)'),
            self::PRICING_POLICY_REFERENCE_ONLY => __('Reference only'),
            self::PRICING_POLICY_CONFIRMED_WITH_SUPPLIER => __('Confirmed with supplier'),
            self::PRICING_POLICY_INTERNAL_ONLY => __('Internal only'),
            self::PRICING_POLICY_VOID => __('Void'),
        ];
    }

    public static function pricingPolicyLabelFor(?string $policy): string
    {
        if ($policy === null || $policy === '') {
            return self::pricingPolicyOptions()[self::PRICING_POLICY_STANDARD];
        }

        return self::pricingPolicyOptions()[$policy] ?? $policy;
    }

    public function ingestionBatch(): BelongsTo
    {
        return $this->belongsTo(IngestionBatch::class);
    }

    public function isManualEntry(): bool
    {
        return $this->entry_source === self::ENTRY_SOURCE_MANUAL;
    }

    public function aiExtraction(): BelongsTo
    {
        return $this->belongsTo(AiExtraction::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('line_no');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function pricingPolicyLabel(): string
    {
        return self::pricingPolicyLabelFor($this->pricing_policy);
    }

    public function isVoidPolicy(): bool
    {
        return ($this->pricing_policy ?? self::PRICING_POLICY_STANDARD) === self::PRICING_POLICY_VOID;
    }

    public function isValidUntilExpired(): bool
    {
        if ($this->valid_until === null) {
            return false;
        }

        return Carbon::today()->gt($this->valid_until);
    }

    public function approvalStatusLabel(): string
    {
        if ($this->isVoidPolicy()) {
            return __('Void');
        }

        if ($this->approved_at === null) {
            return __('Pending approval');
        }

        if ($this->isValidUntilExpired()) {
            return __('Expired');
        }

        return __('Approved');
    }

    public function approvalStatusColor(): string
    {
        if ($this->isVoidPolicy()) {
            return 'gray';
        }

        if ($this->approved_at === null) {
            return 'warning';
        }

        if ($this->isValidUntilExpired()) {
            return 'danger';
        }

        return 'success';
    }

    public function pricingPolicyBadgeColor(): string
    {
        return match ($this->pricing_policy ?? self::PRICING_POLICY_STANDARD) {
            self::PRICING_POLICY_REFERENCE_ONLY => 'info',
            self::PRICING_POLICY_CONFIRMED_WITH_SUPPLIER => 'primary',
            self::PRICING_POLICY_INTERNAL_ONLY => 'gray',
            self::PRICING_POLICY_VOID => 'danger',
            default => 'success',
        };
    }

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'subtotal_before_tax' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'header_snapshot_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
