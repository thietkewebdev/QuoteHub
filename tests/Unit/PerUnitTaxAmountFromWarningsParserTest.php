<?php

namespace Tests\Unit;

use App\Services\AI\Correction\PerUnitTaxAmountFromWarningsParser;
use Tests\TestCase;

class PerUnitTaxAmountFromWarningsParserTest extends TestCase
{
    public function test_parses_thue_dong_vnd_pattern(): void
    {
        $p = new PerUnitTaxAmountFromWarningsParser;
        $n = $p->parse(['Thuế dòng (VNĐ): 332000', 'other']);

        $this->assertEqualsWithDelta(332_000.0, (float) $n, 0.001);
    }

    public function test_parses_grouped_vn_number(): void
    {
        $p = new PerUnitTaxAmountFromWarningsParser;
        $n = $p->parse(['(VNĐ): 1.234.567']);

        $this->assertEqualsWithDelta(1_234_567.0, (float) $n, 0.001);
    }
}
