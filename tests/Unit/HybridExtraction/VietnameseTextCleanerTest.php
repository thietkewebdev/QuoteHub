<?php

namespace Tests\Unit\HybridExtraction;

use App\Services\Quotation\HybridExtraction\VietnameseTextCleaner;
use Tests\TestCase;

class VietnameseTextCleanerTest extends TestCase
{
    public function test_collapses_irregular_spaces(): void
    {
        $cleaner = new VietnameseTextCleaner;
        $out = $cleaner->clean("Máy  in   mã\xC2\xA0vạch");

        $this->assertSame('Máy in mã vạch', $out);
    }

    public function test_applies_glue_phrase_map_from_config(): void
    {
        $cleaner = new VietnameseTextCleaner;
        $out = $cleaner->clean('Hỗtrợinđa dạng mãvạch');

        $this->assertStringContainsString('Hỗ trợ in đa dạng', $out);
        $this->assertStringContainsString('mã vạch', $out);
    }
}
