<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Quotation\PriceHistoryQuery;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLowestVisibleUnitPriceSubqueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_selects_minimum_unit_price_for_approved_manual_mapped_line(): void
    {
        $user = UserFactory::new()->create();

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-P',
            'name' => 'P',
            'slug' => 'p',
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
            'raw_name' => 'Line',
            'quantity' => 1,
            'unit_price' => 199,
            'line_total' => 199,
            'mapped_product_id' => $product->id,
        ]);

        $row = Product::query()
            ->addSelect([
                'lowest_visible_unit_price' => PriceHistoryQuery::lowestVisibleUnitPricePerProductSubquery(),
            ])
            ->whereKey($product->id)
            ->firstOrFail();

        $this->assertSame(199.0, (float) $row->lowest_visible_unit_price);
    }

    public function test_order_by_raw_subquery_runs_without_error(): void
    {
        $user = UserFactory::new()->create();

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'SKU-O',
            'name' => 'O',
            'slug' => 'o',
            'is_active' => true,
        ]);

        $manual = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'S',
            'supplier_quote_number' => 'M2',
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $manual->id,
            'line_no' => 1,
            'raw_name' => 'Line',
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
            'mapped_product_id' => $product->id,
        ]);

        $sub = PriceHistoryQuery::lowestVisibleUnitPricePerProductSubquery();

        $ids = Product::query()
            ->whereKey($product->id)
            ->orderByRaw('('.$sub->toSql().') asc', $sub->getBindings())
            ->pluck('id')
            ->all();

        $this->assertSame([(int) $product->id], array_map('intval', $ids));
    }
}
