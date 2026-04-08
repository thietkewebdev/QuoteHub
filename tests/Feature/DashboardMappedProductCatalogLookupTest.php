<?php

namespace Tests\Feature;

use App\Models\IngestionBatch;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Operations\DashboardMappedProductBestPrices;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMappedProductCatalogLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_lists_active_products_by_name_even_without_price_history(): void
    {
        $user = UserFactory::new()->create();

        $zebra = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'Z-1',
            'name' => 'Zebra item',
            'slug' => 'zebra-item',
            'is_active' => true,
        ]);

        $alpha = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'A-1',
            'name' => 'Alpha item',
            'slug' => 'alpha-item',
            'is_active' => true,
        ]);

        $svc = app(DashboardMappedProductBestPrices::class);
        $rows = $svc->catalogLookupRows('', 10);

        $this->assertCount(2, $rows);
        $this->assertSame((int) $alpha->id, $rows[0]->product_id);
        $this->assertNull($rows[0]->best_unit_price);
        $this->assertSame((int) $zebra->id, $rows[1]->product_id);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'email',
            'received_at' => now(),
            'uploaded_by' => $user->id,
            'status' => 'approved',
            'file_count' => 0,
        ]);

        $q = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Supplier',
            'supplier_quote_number' => 'Q1',
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'quote_date' => now()->subDay(),
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $q->id,
            'line_no' => 1,
            'raw_name' => 'Line',
            'quantity' => 1,
            'unit_price' => 42.5,
            'line_total' => 42.5,
            'mapped_product_id' => $alpha->id,
        ]);

        $rows = $svc->catalogLookupRows('', 10);
        $alphaRow = $rows->firstWhere('product_id', (int) $alpha->id);
        $this->assertNotNull($alphaRow);
        $this->assertSame(42.5, $alphaRow->best_unit_price);
    }

    public function test_search_matches_case_insensitive_on_sqlite(): void
    {
        Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-UP',
            'name' => 'CamelCase Product',
            'slug' => 'camelcase-product',
            'is_active' => true,
        ]);

        $svc = app(DashboardMappedProductBestPrices::class);
        $rows = $svc->catalogLookupRows('camelcase', 10);

        $this->assertCount(1, $rows);
        $this->assertSame('CamelCase Product', $rows[0]->product_name);
    }

    public function test_search_matches_technical_specifications(): void
    {
        Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'X-9',
            'name' => 'Obscure widget',
            'slug' => 'obscure-widget',
            'specs_text' => 'USB 3.0 interface 500 DPI scanner',
            'is_active' => true,
        ]);

        $svc = app(DashboardMappedProductBestPrices::class);
        $rows = $svc->catalogLookupRows('scanner', 10);

        $this->assertCount(1, $rows);
        $this->assertSame('Obscure widget', $rows[0]->product_name);
        $this->assertStringContainsString('scanner', (string) $rows[0]->specs_text);
    }
}
