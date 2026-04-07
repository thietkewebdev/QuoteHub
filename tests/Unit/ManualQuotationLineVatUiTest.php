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
        $this->assertSame(20_916_000.0, (float) $bag['vat_amount_display']);
        $this->assertSame(282_366_000.0, (float) $bag['line_gross_display']);
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
        $this->assertSame(100.0, (float) $bag['vat_amount_display']);
        $this->assertSame(1099.0, (float) $bag['line_gross_display']);
    }

    public function test_apply_inclusive_gross_derives_excl_vat_and_unit_price(): void
    {
        $bag = [
            'quantity' => 63,
            'unit_price' => 0,
            'vat_percent' => 8,
            'line_total' => null,
            'vat_amount_display' => null,
            'line_gross_display' => 282_366_000,
        ];
        $set = function (string $key, mixed $value) use (&$bag): void {
            $bag[$key] = $value;
        };
        $get = fn (string $key): mixed => $bag[$key] ?? null;

        ManualQuotationLineVatUi::applyInclusiveGross($set, $get);

        $this->assertSame(261_450_000.0, (float) $bag['line_total']);
        $this->assertSame(20_916_000.0, (float) $bag['vat_amount_display']);
        $this->assertEqualsWithDelta(4_150_000.0, (float) $bag['unit_price'], 0.01);
        $this->assertSame(282_366_000.0, (float) $bag['line_gross_display']);
    }

    public function test_apply_manual_vat_amount_sets_gross(): void
    {
        $bag = [
            'line_total' => 1_000_000,
            'vat_amount_display' => 80_000,
            'line_gross_display' => null,
        ];
        $set = function (string $key, mixed $value) use (&$bag): void {
            $bag[$key] = $value;
        };
        $get = fn (string $key): mixed => $bag[$key] ?? null;

        ManualQuotationLineVatUi::applyManualVatAmount($set, $get);

        $this->assertSame(1_080_000.0, (float) $bag['line_gross_display']);
    }
}
