<?php

namespace App\Services\Operations;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

/**
 * Read models for procurement / buying reporting widgets (PO due dates, quote validity, PO coverage, last buys).
 */
final class ProcurementReportingQueries
{
    /**
     * POs in draft or issued with an expected date: overdue or within the next N days (inclusive).
     *
     * @return EloquentCollection<int, PurchaseOrder>
     */
    public function purchaseOrdersDueOrOverdue(int $withinDays = 14, int $limit = 40): EloquentCollection
    {
        $today = Carbon::today()->toDateString();
        $until = Carbon::today()->addDays($withinDays)->toDateString();

        return PurchaseOrder::query()
            ->with(['supplier'])
            ->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ISSUED])
            ->whereNotNull('expected_delivery_date')
            ->where(function (Builder $q) use ($today, $until): void {
                $q->whereDate('expected_delivery_date', '<', $today)
                    ->orWhereBetween('expected_delivery_date', [$today, $until]);
            })
            ->orderBy('expected_delivery_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Approved quotations with a validity end date in the past or within the next N days.
     *
     * @return EloquentCollection<int, Quotation>
     */
    public function quotationsValidityWindow(int $withinDays = 14, int $limit = 40): EloquentCollection
    {
        $today = Carbon::today()->toDateString();
        $until = Carbon::today()->addDays($withinDays)->toDateString();

        return Quotation::query()
            ->whereNotNull('approved_at')
            ->whereNotNull('valid_until')
            ->where(function (Builder $q) use ($today, $until): void {
                $q->whereDate('valid_until', '<', $today)
                    ->orWhereBetween('valid_until', [$today, $until]);
            })
            ->orderBy('valid_until')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Approved quotations linked to a catalog supplier, with lines, and no purchase order yet.
     *
     * @return EloquentCollection<int, Quotation>
     */
    public function approvedQuotationsWithoutPurchaseOrder(int $limit = 40): EloquentCollection
    {
        return Quotation::query()
            ->whereNotNull('approved_at')
            ->whereNotNull('supplier_id')
            ->whereHas('items')
            ->whereDoesntHave('purchaseOrders')
            ->with(['supplier'])
            ->orderByDesc('approved_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Latest purchase line per (product_id, supplier_id) from recent POs, optionally filtered by product name/SKU.
     *
     * @return list<array{line: PurchaseOrderLine, key: string}>
     */
    public function latestPurchaseLineRowsByProductAndSupplier(?string $search, int $limit = 30): array
    {
        $search = trim((string) $search);

        $query = PurchaseOrderLine::query()
            ->select('purchase_order_lines.*')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereNotNull('purchase_order_lines.product_id')
            ->whereIn('purchase_orders.status', [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_ISSUED,
                PurchaseOrder::STATUS_COMPLETED,
            ])
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_orders.id')
            ->orderByDesc('purchase_order_lines.id')
            ->with(['product', 'purchaseOrder.supplier']);

        if ($search !== '') {
            $productIds = Product::query()
                ->where(function (Builder $q) use ($search): void {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                })
                ->limit(200)
                ->pluck('id')
                ->all();

            if ($productIds === []) {
                return [];
            }

            $query->whereIn('purchase_order_lines.product_id', $productIds);
        }

        $lines = $query->limit(800)->get();

        $seen = [];
        $rows = [];
        foreach ($lines as $line) {
            $supplierId = $line->purchaseOrder?->supplier_id;
            if ($supplierId === null || $line->product_id === null) {
                continue;
            }

            $key = $line->product_id.'-'.$supplierId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = ['line' => $line, 'key' => $key];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }
}
