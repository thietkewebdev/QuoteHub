<?php

namespace Tests\Unit;

use App\Services\AI\Segmentation\OcrTableRegionSegmenter;
use Tests\TestCase;

class OcrTableRegionSegmenterTest extends TestCase
{
    public function test_extracts_region_between_header_and_footer(): void
    {
        config(['quotation_ai.table_segmentation.enabled' => true]);

        $ocr = <<<'TXT'
Công ty ABC
BÁO GIÁ

STT | Tên hàng | SL | Đơn giá | Thành tiền
1 | Máy in | 2 | 1000000 | 2000000
2 | Giấy | 10 | 50000 | 500000

Tổng cộng 2.500.000
TXT;

        [$region, $meta] = (new OcrTableRegionSegmenter)->extractLineItemRegion($ocr);

        $this->assertFalse($meta['used_full_document']);
        $this->assertSame('segmented', $meta['mode']);
        $this->assertStringContainsString('STT', $region);
        $this->assertStringContainsString('Máy in', $region);
        $this->assertStringNotContainsString('Tổng cộng', $region);
    }

    public function test_falls_back_to_full_document_when_no_header(): void
    {
        config(['quotation_ai.table_segmentation.enabled' => true]);

        $ocr = "Random text without table headers\n123 456";
        [$region, $meta] = (new OcrTableRegionSegmenter)->extractLineItemRegion($ocr);

        $this->assertTrue($meta['used_full_document']);
        $this->assertSame($ocr, $region);
    }
}
