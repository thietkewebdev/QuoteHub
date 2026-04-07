<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a {@see PurchaseOrder} with lines copied from an approved quotation (for procurement traceability).
 */
final class CreatePurchaseOrderFromQuotationAction
{
    public function execute(Quotation $quotation, ?User $user): PurchaseOrder
    {
        if ($quotation->approved_at === null) {
            throw new InvalidArgumentException(__('Only approved quotations can create a purchase order.'));
        }

        if ($quotation->supplier_id === null) {
            throw new InvalidArgumentException(__('Link this quotation to a catalog supplier before creating a purchase order.'));
        }

        $quotation->load(['items' => fn ($query) => $query->orderBy('line_no')]);

        if ($quotation->items->isEmpty()) {
            throw new InvalidArgumentException(__('This quotation has no line items.'));
        }

        return DB::transaction(function () use ($quotation, $user): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $quotation->supplier_id,
                'quotation_id' => $quotation->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => now()->toDateString(),
                'currency' => filled($quotation->currency) ? (string) $quotation->currency : 'VND',
                'created_by' => $user?->id,
            ]);

            PurchaseOrderLine::withoutEvents(function () use ($order, $quotation): void {
                foreach ($quotation->items as $item) {
                    $lineTotal = $item->line_total;
                    if ($lineTotal === null && $item->quantity !== null && $item->unit_price !== null) {
                        $lineTotal = round((float) $item->quantity * (float) $item->unit_price, 4);
                    }

                    $order->lines()->create([
                        'line_no' => (int) $item->line_no,
                        'quotation_item_id' => $item->id,
                        'product_id' => $item->mapped_product_id,
                        'description' => $item->displayLabel(),
                        'unit' => filled($item->unit) ? (string) $item->unit : null,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'vat_percent' => $item->vat_percent,
                        'line_total' => $lineTotal,
                    ]);
                }
            });

            $order->recalculateTotals();

            return $order->fresh(['lines', 'supplier', 'quotation']);
        });
    }
}
