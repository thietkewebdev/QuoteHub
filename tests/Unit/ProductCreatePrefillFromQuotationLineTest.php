<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\QuotationItem;
use App\Services\Catalog\ProductCreatePrefillFromQuotationLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductCreatePrefillFromQuotationLineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_brand_from_token_in_raw_name_when_brand_column_empty(): void
    {
        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => 'TOA',
            'slug' => 'toa',
            'code' => null,
            'is_active' => true,
        ]);

        $item = new QuotationItem([
            'raw_name' => 'Loa nén 10 W có biến áp TOA SC-610M',
            'raw_model' => 'SC-610M',
            'brand' => '',
        ]);

        $this->assertSame($brand->id, ProductCreatePrefillFromQuotationLine::resolveBrandId($item));
    }

    #[Test]
    public function it_resolves_brand_by_code_token_in_title(): void
    {
        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => 'Toa Corporation',
            'slug' => 'toa-corporation',
            'code' => 'TOA',
            'is_active' => true,
        ]);

        $item = new QuotationItem([
            'raw_name' => 'Loa TOA SC-610M',
            'raw_model' => '',
            'brand' => '',
        ]);

        $this->assertSame($brand->id, ProductCreatePrefillFromQuotationLine::resolveBrandId($item));
    }

    #[Test]
    public function it_creates_catalog_brand_when_latin_code_appears_in_line_and_none_exists(): void
    {
        $this->assertSame(0, Brand::query()->count());

        $item = new QuotationItem([
            'raw_name' => 'Loa nén 10 W có biến áp TOA SC-610M',
            'raw_model' => 'SC-610M',
            'brand' => '',
        ]);

        $id = ProductCreatePrefillFromQuotationLine::resolveBrandId($item);
        $this->assertNotNull($id);

        $brand = Brand::query()->findOrFail($id);
        $this->assertSame('TOA', $brand->name);
        $this->assertTrue($brand->is_active);
        $this->assertSame('TOA', $brand->code);
    }

    #[Test]
    public function it_uses_brand_from_line_snapshot_when_columns_are_empty(): void
    {
        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => 'TOA',
            'slug' => 'toa',
            'code' => null,
            'is_active' => true,
        ]);

        $item = new QuotationItem([
            'raw_name' => '',
            'raw_model' => '',
            'brand' => '',
            'line_snapshot_json' => [
                'brand' => 'TOA',
                'raw_name' => 'Loa x',
            ],
        ]);

        $this->assertSame($brand->id, ProductCreatePrefillFromQuotationLine::resolveBrandId($item));
    }
}
