<?php

namespace Tests\Feature;

use App\Actions\Supplier\LinkApprovedQuotationsToSuppliersByNameAction;
use App\Actions\Supplier\SyncSuppliersFromApprovedQuotationsAction;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRecallActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_suppliers_from_distinct_approved_names_without_touching_quotations(): void
    {
        $user = User::factory()->create();
        $q1 = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Công Ty Alpha',
            'supplier_quote_number' => 'A1',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => 'manual_entry',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'CÔNG TY ALPHA',
            'supplier_quote_number' => 'A2',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => 'manual_entry',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $beforeName = $q1->fresh()->supplier_name;

        $result = app(SyncSuppliersFromApprovedQuotationsAction::class)->execute();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['already_existed']);
        $this->assertSame(2, $result['distinct_names']);
        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame($beforeName, $q1->fresh()->supplier_name);
        $this->assertNull($q1->fresh()->supplier_id);
    }

    public function test_link_sets_supplier_id_only_when_normalized_match(): void
    {
        $user = User::factory()->create();
        Supplier::query()->create([
            'name' => 'Beta Co',
            'code' => null,
            'is_active' => true,
        ]);

        $q = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => '  BETA co  ',
            'supplier_quote_number' => 'B1',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => 'manual_entry',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $result = app(LinkApprovedQuotationsToSuppliersByNameAction::class)->execute();

        $this->assertSame(1, $result['updated']);
        $q->refresh();
        $this->assertNotNull($q->supplier_id);
        $this->assertSame('  BETA co  ', $q->supplier_name);
    }
}
