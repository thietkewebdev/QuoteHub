<?php

namespace Tests\Feature;

use App\Actions\Quotation\ApproveManualQuotationAction;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveManualQuotationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_manual_quotation_without_batch_or_ai(): void
    {
        $user = User::factory()->create();
        $draft = QuotationReviewDraft::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'payload_json' => [],
            'review_status' => QuotationReviewDraft::STATUS_DRAFT,
        ]);

        $payload = [
            'supplier_id' => null,
            'supplier_name' => 'ACME VN',
            'supplier_quote_number' => 'BG-001',
            'quote_date' => '2026-04-05',
            'contact_person' => 'Anh Nam',
            'notes' => 'Ghi chú',
            'total_amount' => null,
            'reviewer_notes' => '',
            'items' => [
                [
                    'raw_name' => 'Máy in',
                    'raw_model' => 'X1',
                    'brand' => 'HP',
                    'unit' => 'cái',
                    'quantity' => 2,
                    'unit_price' => 1000000,
                    'vat_percent' => 10,
                    'line_total' => null,
                    'specs_text' => '',
                    'mapped_product_id' => null,
                ],
            ],
        ];

        $quotation = app(ApproveManualQuotationAction::class)->execute($draft, $user, $payload);

        $this->assertNull($quotation->ingestion_batch_id);
        $this->assertNull($quotation->ai_extraction_id);
        $this->assertSame(Quotation::ENTRY_SOURCE_MANUAL, $quotation->entry_source);
        $this->assertSame('ACME VN', $quotation->supplier_name);
        $this->assertCount(1, $quotation->items);
        $this->assertNull($quotation->items->first()->line_snapshot_json);

        $draft->refresh();
        $this->assertSame(QuotationReviewDraft::STATUS_APPROVED, $draft->review_status);
        $this->assertSame($quotation->id, $draft->approved_quotation_id);
    }

    public function test_rejects_approve_when_supplier_name_is_blank(): void
    {
        $user = User::factory()->create();
        $draft = QuotationReviewDraft::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'payload_json' => [],
            'review_status' => QuotationReviewDraft::STATUS_DRAFT,
        ]);

        $payload = [
            'supplier_id' => null,
            'supplier_name' => '   ',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => '',
            'total_amount' => null,
            'reviewer_notes' => '',
            'items' => [
                ['raw_name' => 'A', 'raw_model' => '', 'brand' => '', 'unit' => '', 'quantity' => 1, 'unit_price' => 1, 'vat_percent' => null, 'line_total' => 1, 'specs_text' => '', 'mapped_product_id' => null],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);

        app(ApproveManualQuotationAction::class)->execute($draft, $user, $payload);
    }

    public function test_skips_blank_line_rows_when_numbering(): void
    {
        $user = User::factory()->create();
        $draft = QuotationReviewDraft::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'payload_json' => [],
            'review_status' => QuotationReviewDraft::STATUS_DRAFT,
        ]);

        $payload = [
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => '',
            'total_amount' => null,
            'reviewer_notes' => '',
            'items' => [
                ['raw_name' => 'A', 'raw_model' => '', 'brand' => '', 'unit' => '', 'quantity' => 1, 'unit_price' => 1, 'vat_percent' => null, 'line_total' => 1, 'specs_text' => '', 'mapped_product_id' => null],
                ['raw_name' => '', 'raw_model' => '', 'brand' => '', 'unit' => '', 'quantity' => 9, 'unit_price' => 9, 'vat_percent' => null, 'line_total' => 9, 'specs_text' => '', 'mapped_product_id' => null],
                ['raw_name' => 'B', 'raw_model' => '', 'brand' => '', 'unit' => '', 'quantity' => 1, 'unit_price' => 2, 'vat_percent' => null, 'line_total' => 2, 'specs_text' => '', 'mapped_product_id' => null],
            ],
        ];

        $quotation = app(ApproveManualQuotationAction::class)->execute($draft, $user, $payload);

        $this->assertCount(2, $quotation->items);
        $this->assertSame(1, $quotation->items[0]->line_no);
        $this->assertSame('A', $quotation->items[0]->raw_name);
        $this->assertSame(2, $quotation->items[1]->line_no);
        $this->assertSame('B', $quotation->items[1]->raw_name);
    }

    public function test_preserves_existing_mapped_product_id_and_skips_auto_link(): void
    {
        $user = User::factory()->create();
        $draft = QuotationReviewDraft::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'payload_json' => [],
            'review_status' => QuotationReviewDraft::STATUS_DRAFT,
        ]);

        $prelinked = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'PRE-LINKED',
            'name' => 'Pre',
            'slug' => 'pre-linked',
            'is_active' => true,
        ]);

        Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'OTHER-SKU-100',
            'name' => 'Other',
            'slug' => 'other-sku-100',
            'is_active' => true,
        ]);

        $payload = [
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => '',
            'total_amount' => null,
            'reviewer_notes' => '',
            'items' => [
                [
                    'raw_name' => 'Line',
                    'raw_model' => 'OTHER-SKU-100',
                    'brand' => '',
                    'unit' => '',
                    'quantity' => 1,
                    'unit_price' => 1,
                    'vat_percent' => null,
                    'line_total' => 1,
                    'specs_text' => '',
                    'mapped_product_id' => $prelinked->id,
                ],
            ],
        ];

        $quotation = app(ApproveManualQuotationAction::class)->execute($draft, $user, $payload);

        $line = $quotation->items->first();
        $this->assertSame($prelinked->id, (int) $line->mapped_product_id);
    }
}
