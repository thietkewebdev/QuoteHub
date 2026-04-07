<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Operations\ProcurementReportingQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcurementReportingQueriesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function approved_quotations_without_purchase_order_lists_matching_rows(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::query()->create(['name' => 'S', 'is_active' => true]);

        $q = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $q->id,
            'line_no' => 1,
            'raw_name' => 'Item',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $rows = app(ProcurementReportingQueries::class)->approvedQuotationsWithoutPurchaseOrder(10);

        $this->assertCount(1, $rows);
        $this->assertSame($q->id, $rows->first()->id);
    }

    #[Test]
    public function latest_purchase_rows_group_by_product_and_supplier(): void
    {
        $supplier = Supplier::query()->create(['name' => 'S2', 'is_active' => true]);
        $product = Product::query()->create([
            'name' => 'Widget Z',
            'sku' => 'WZ-1',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_ISSUED,
            'order_date' => now()->toDateString(),
            'currency' => 'VND',
        ]);

        PurchaseOrderLine::withoutEvents(function () use ($po, $product): void {
            $po->lines()->create([
                'line_no' => 1,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 5000,
                'line_total' => 5000,
            ]);
        });
        $po->recalculateTotals();

        $rows = app(ProcurementReportingQueries::class)->latestPurchaseLineRowsByProductAndSupplier('Widget', 10);

        $this->assertCount(1, $rows);
        $this->assertSame(5000.0, (float) $rows[0]['line']->unit_price);
    }
}
