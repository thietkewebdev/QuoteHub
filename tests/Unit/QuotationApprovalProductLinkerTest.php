<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\IngestionBatch;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\Quotation\QuotationApprovalProductLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationApprovalProductLinkerTest extends TestCase
{
    use RefreshDatabase;

    private function makeItemForQuotation(Quotation $quotation, string $rawName, string $rawModel, ?int $mappedId = null): QuotationItem
    {
        return QuotationItem::query()->create([
            'quotation_id' => $quotation->id,
            'line_no' => 1,
            'raw_name' => $rawName,
            'raw_name_raw' => null,
            'raw_model' => $rawModel,
            'brand' => '',
            'unit' => '',
            'quantity' => 1,
            'unit_price' => 1,
            'vat_percent' => null,
            'line_total' => 1,
            'specs_text' => '',
            'line_snapshot_json' => null,
            'mapped_product_id' => $mappedId,
            'mapped_at' => null,
            'mapped_by' => null,
        ]);
    }

    public function test_auto_links_when_top_suggestion_score_is_at_least_90(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
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
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'EXACT-SKU-99',
            'name' => 'Catalog item',
            'slug' => 'catalog-item-99',
            'is_active' => true,
        ]);

        $item = $this->makeItemForQuotation($quotation, 'Line label', 'EXACT-SKU-99');

        app(QuotationApprovalProductLinker::class)->handle($item, $user);

        $item->refresh();
        $this->assertSame($product->id, (int) $item->mapped_product_id);
        $this->assertNotNull($item->mapped_at);
        $this->assertSame($user->id, (int) $item->mapped_by);
    }

    public function test_does_not_link_when_top_suggestion_score_is_below_90(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
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
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'OTHER',
            'name' => 'Other',
            'slug' => 'other-88',
            'is_active' => true,
        ]);

        ProductAlias::query()->create([
            'product_id' => $product->id,
            'alias' => 'alias-model-88',
            'alias_type' => null,
        ]);

        $item = $this->makeItemForQuotation($quotation, 'x', 'alias-model-88');

        app(QuotationApprovalProductLinker::class)->handle($item, $user);

        $item->refresh();
        $this->assertNull($item->mapped_product_id);
    }

    public function test_skips_when_item_already_mapped(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
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
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $kept = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'KEPT-1',
            'name' => 'Kept',
            'slug' => 'kept-1',
            'is_active' => true,
        ]);

        Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'EXACT-SKU-100',
            'name' => 'Would match',
            'slug' => 'would-match',
            'is_active' => true,
        ]);

        $item = $this->makeItemForQuotation($quotation, 'x', 'EXACT-SKU-100', $kept->id);

        app(QuotationApprovalProductLinker::class)->handle($item, $user);

        $item->refresh();
        $this->assertSame($kept->id, (int) $item->mapped_product_id);
    }

    public function test_does_not_auto_link_exact_sku_when_product_has_brand_but_line_text_has_no_brand_signal(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Other supplier',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => 'TOA',
            'slug' => 'toa',
            'code' => 'TOA',
            'is_active' => true,
        ]);

        Product::query()->create([
            'supplier_id' => null,
            'brand_id' => $brand->id,
            'product_category_id' => null,
            'sku' => 'SC-610M',
            'name' => 'Loa TOA SC-610M',
            'slug' => 'loa-toa-sc-610m',
            'is_active' => true,
        ]);

        $item = $this->makeItemForQuotation($quotation, 'Hạ tầng âm thanh khác', 'SC-610M');

        app(QuotationApprovalProductLinker::class)->handle($item, $user);

        $item->refresh();
        $this->assertNull($item->mapped_product_id);
    }

    public function test_auto_links_exact_sku_when_product_has_brand_and_line_text_contains_brand(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);
        $quotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'NCC A',
            'supplier_quote_number' => '',
            'quote_date' => null,
            'contact_person' => '',
            'notes' => null,
            'currency' => 'VND',
            'subtotal_before_tax' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'header_snapshot_json' => null,
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => 'TOA',
            'slug' => 'toa-2',
            'code' => 'TOA',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => $brand->id,
            'product_category_id' => null,
            'sku' => 'SC-610M',
            'name' => 'Loa TOA',
            'slug' => 'loa-toa-2',
            'is_active' => true,
        ]);

        $item = $this->makeItemForQuotation($quotation, 'Loa nén TOA SC-610M', 'SC-610M');

        app(QuotationApprovalProductLinker::class)->handle($item, $user);

        $item->refresh();
        $this->assertSame($product->id, (int) $item->mapped_product_id);
    }
}
