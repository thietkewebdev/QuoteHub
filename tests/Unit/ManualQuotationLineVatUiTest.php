<?php

namespace Tests\Unit;

use App\Support\Quotation\ManualQuotationLineVatUi;
use PHPUnit\Framework\TestCase;

class ManualQuotationLineVatUiTest extends TestCase
{
    public function test_sync_sets_subtotal_vat_and_gross_when_qty_and_price_present(): void
    {
        $bag = [
            'quantity' => 63,
            'unit_price' => 4_150_000,
            'vat_percent' => 8,
            'line_total' => null,
            'vat_amount_display' => null,
            'line_gross_display' => null,
        ];
        $set = function (string $key, mixed $value) use (&$bag): void {
            $bag[$key] = $value;
        };
        $get = fn (string $key): mixed => $bag[$key] ?? null;

        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);

        $this->assertEqualsWithDelta(261_450_000.0, (float) $bag['line_total'], 0.001);
        $this->assertEqualsWithDelta(20_916_000.0, (float) $bag['vat_amount_display'], 0.001);
        $this->assertEqualsWithDelta(282_366_000.0, (float) $bag['line_gross_display'], 0.001);
    }

    public function test_sync_review_mode_does_not_overwrite_line_total(): void
    {
        $bag = [
            'quantity' => 10,
            'unit_price' => 100,
            'vat_percent' => 10,
            'line_total' => 999,
            'vat_amount_display' => null,
            'line_gross_display' => null,
        ];
        $set = function (string $key, mixed $value) use (&$bag): void {
            $bag[$key] = $value;
        };
        $get = fn (string $key): mixed => $bag[$key] ?? null;

        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: false);

        $this->assertEqualsWithDelta(999.0, (float) $bag['line_total'], 0.001);
        $this->assertEqualsWithDelta(99.9, (float) $bag['vat_amount_display'], 0.001);
        $this->assertEqualsWithDelta(1098.9, (float) $bag['line_gross_display'], 0.001);
    }
}
