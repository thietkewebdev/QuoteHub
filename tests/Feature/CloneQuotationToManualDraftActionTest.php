<?php

namespace Tests\Feature;

use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloneQuotationToManualDraftActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clones_approved_quotation_into_manual_draft_payload(): void
    {
        $user = User::factory()->create();
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Supplier A',
            'supplier_quote_number' => 'BG-99',
            'quote_date' => '2026-01-15',
            'contact_person' => 'Liên hệ',
            'notes' => 'Ghi chú',
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => 2500,
            'header_snapshot_json' => ['x' => 1],
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        QuotationItem::query()->create([
            'quotation_id' => $quotation->id,
            'line_no' => 1,
            'raw_name' => 'Line one',
            'raw_model' => 'M1',
            'brand' => 'B',
            'unit' => 'u',
            'quantity' => 2,
            'unit_price' => 1000,
            'vat_percent' => 10,
            'line_total' => 2000,
            'specs_text' => 'spec',
            'mapped_product_id' => null,
        ]);

        $draft = app(CloneQuotationToManualDraftAction::class)->execute($quotation, $user);

        $this->assertNull($draft->ingestion_batch_id);
        $this->assertNull($draft->approved_quotation_id);
        $payload = $draft->payload_json;
        $this->assertSame($quotation->id, $payload['cloned_from_quotation_id']);
        $this->assertSame('Supplier A', $payload['supplier_name']);
        $this->assertSame('BG-99', $payload['supplier_quote_number']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('Line one', $payload['items'][0]['raw_name']);
        $this->assertEqualsWithDelta(2000.0, (float) $payload['items'][0]['line_total'], 0.001);
    }

    public function test_rejects_unapproved_quotation(): void
    {
        $user = User::factory()->create();
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(CloneQuotationToManualDraftAction::class)->execute($quotation, $user);
    }
}
