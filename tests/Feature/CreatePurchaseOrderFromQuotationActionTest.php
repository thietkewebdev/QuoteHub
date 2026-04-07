<?php

namespace Tests\Feature;

use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuotationAction;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreatePurchaseOrderFromQuotationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_purchase_order_with_lines_from_approved_quotation(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::query()->create([
            'name' => 'NCC Test PO',
            'code' => 'ncc-po',
            'is_active' => true,
        ]);

        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_quote_number' => 'BG-PO-1',
            'quote_date' => now()->toDateString(),
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'currency' => 'VND',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $quotation->id,
            'line_no' => 1,
            'raw_name' => 'Widget A',
            'raw_model' => 'W-A',
            'brand' => 'ACME',
            'unit' => 'cái',
            'quantity' => 2,
            'unit_price' => 150000,
            'vat_percent' => 10,
            'line_total' => 300000,
        ]);

        $order = app(CreatePurchaseOrderFromQuotationAction::class)->execute($quotation, $user);

        $this->assertInstanceOf(PurchaseOrder::class, $order);
        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame($quotation->id, $order->quotation_id);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->assertNotEmpty($order->po_number);
        $this->assertCount(1, $order->lines);
        $line = $order->lines->first();
        $this->assertSame('Widget A', $line->description);
        $this->assertEquals(300000.0, (float) $line->line_total);
        $this->assertNotNull($order->total_amount);
    }

    public function test_rejects_when_quotation_not_approved(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::query()->create([
            'name' => 'NCC',
            'is_active' => true,
        ]);

        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_at' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(CreatePurchaseOrderFromQuotationAction::class)->execute($quotation, $user);
    }

    public function test_rejects_when_supplier_not_linked(): void
    {
        $user = User::factory()->create();

        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Loose name',
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $quotation->id,
            'line_no' => 1,
            'raw_name' => 'X',
            'quantity' => 1,
            'unit_price' => 1,
            'line_total' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(CreatePurchaseOrderFromQuotationAction::class)->execute($quotation, $user);
    }
}
