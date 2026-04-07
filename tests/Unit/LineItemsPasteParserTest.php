<?php

namespace Tests\Unit;

use App\Services\Quotation\LineItemsPasteParser;
use Tests\TestCase;

class LineItemsPasteParserTest extends TestCase
{
    public function test_parses_tab_separated_rows(): void
    {
        $raw = "Máy in\tX1\tHP\tcái\t2\t1000000\t10\t\t\nGiấy\t\t\tcuộn\t5\t50000";
        $rows = (new LineItemsPasteParser)->parse($raw);

        $this->assertCount(2, $rows);
        $this->assertSame('Máy in', $rows[0]['raw_name']);
        $this->assertSame('X1', $rows[0]['raw_model']);
        $this->assertSame('HP', $rows[0]['brand']);
        $this->assertSame('cái', $rows[0]['unit']);
        $this->assertEqualsWithDelta(2.0, (float) $rows[0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(1_000_000.0, (float) $rows[0]['unit_price'], 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $rows[0]['vat_percent'], 0.001);
        $this->assertNull($rows[0]['mapped_product_id']);
    }

    public function test_parses_vietnamese_grouped_numbers(): void
    {
        $raw = "A\t\t\t\t1\t1.234.567\t\t";
        $rows = (new LineItemsPasteParser)->parse($raw);
        $this->assertEqualsWithDelta(1_234_567.0, (float) $rows[0]['unit_price'], 0.001);
    }

    public function test_skip_first_line(): void
    {
        $raw = "Tên\tSL\tGiá\nHàng A\t1\t100";
        $rows = (new LineItemsPasteParser)->parse($raw, true);
        $this->assertCount(1, $rows);
        $this->assertSame('Hàng A', $rows[0]['raw_name']);
    }
}
