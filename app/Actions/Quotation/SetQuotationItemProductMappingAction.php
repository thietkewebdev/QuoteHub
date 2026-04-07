<?php

namespace App\Actions\Quotation;

use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\QuotationItemProductMappingAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SetQuotationItemProductMappingAction
{
    public function execute(QuotationItem $item, User $user, ?int $productId): void
    {
        if ($productId !== null) {
            $exists = Product::query()->whereKey($productId)->where('is_active', true)->exists();
            if (! $exists) {
                throw new InvalidArgumentException(__('Unknown or inactive product.'));
            }
        }

        DB::transaction(function () use ($item, $user, $productId): void {
            $item->forceFill([
                'mapped_product_id' => $productId,
                'mapped_at' => $productId !== null ? now() : null,
                'mapped_by' => $productId !== null ? $user->id : null,
            ])->save();

            QuotationItemProductMappingAudit::query()->create([
                'quotation_item_id' => $item->id,
                'product_id' => $productId,
                'user_id' => $user->id,
                'action' => $productId !== null
                    ? QuotationItemProductMappingAudit::ACTION_SET
                    : QuotationItemProductMappingAudit::ACTION_CLEAR,
            ]);
        });
    }
}
