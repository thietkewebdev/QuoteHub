<?php

namespace Tests\Feature;

use App\Models\IngestionBatch;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Quotation\ProductPriceHistoryQuery;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_product_returns_only_mapped_visible_lines(): void
    {
        $user = UserFactory::new()->create();

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-1',
            'name' => 'Widget',
            'slug' => 'widget',
            'is_active' => true,
        ]);

        $otherProduct = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-2',
            'name' => 'Other',
            'slug' => 'other',
            'is_active' => true,
        ]);

        $manual = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => 'M1',
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $manual->id,
            'line_no' => 1,
            'raw_name' => 'Line A',
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
            'mapped_product_id' => $product->id,
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $manual->id,
            'line_no' => 2,
            'raw_name' => 'Line B',
            'quantity' => 1,
            'unit_price' => 20,
            'line_total' => 20,
            'mapped_product_id' => $otherProduct->id,
        ]);

        $this->assertSame(1, (int) ProductPriceHistoryQuery::forProduct($product->id)->count());
        $this->assertSame('Line A', (string) ProductPriceHistoryQuery::forProduct($product->id)->firstOrFail()->raw_name);
    }

    public function test_ai_line_excluded_when_batch_missing(): void
    {
        $user = UserFactory::new()->create();

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-X',
            'name' => 'X',
            'slug' => 'x',
            'is_active' => true,
        ]);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'email',
            'received_at' => now(),
            'uploaded_by' => $user->id,
            'status' => 'approved',
            'file_count' => 0,
        ]);

        $ai = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => 'AI1',
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $ai->id,
            'line_no' => 1,
            'raw_name' => 'AI mapped',
            'quantity' => 1,
            'unit_price' => 5,
            'line_total' => 5,
            'mapped_product_id' => $product->id,
        ]);

        $this->assertSame(1, (int) ProductPriceHistoryQuery::forProduct($product->id)->count());

        $batch->delete();

        $this->assertSame(0, (int) ProductPriceHistoryQuery::forProduct($product->id)->count());
    }
}
