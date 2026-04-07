<?php

namespace Tests\Unit\HybridExtraction;

use App\Services\Quotation\HybridExtraction\VatInferenceService;
use Tests\TestCase;

class VatInferenceServiceTest extends TestCase
{
    public function test_infers_eight_percent_and_sets_line_totals(): void
    {
        $items = [[
            'quantity' => 63,
            'unit_price' => 4_150_000.0,
            'tax_per_unit' => 332_000.0,
            'vat_percent' => null,
            'line_total' => null,
            'warnings' => [],
        ]];

        (new VatInferenceService)->apply($items);

        $this->assertEqualsWithDelta(8.0, (float) $items[0]['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(4_482_000.0, (float) $items[0]['unit_price_after_tax'], 0.001);
        $this->assertEqualsWithDelta(261_450_000.0, (float) $items[0]['line_total'], 0.001);
        $this->assertEqualsWithDelta(282_366_000.0, (float) $items[0]['line_total_after_tax'], 0.001);
    }

    public function test_infers_ten_percent_when_ratio_closer_to_ten(): void
    {
        $unit = 1_000_000.0;
        $tax = $unit * 0.10;
        $items = [[
            'quantity' => 5,
            'unit_price' => $unit,
            'tax_per_unit' => $tax,
            'warnings' => [],
        ]];

        (new VatInferenceService)->apply($items);

        $this->assertEqualsWithDelta(10.0, (float) $items[0]['vat_percent'], 0.001);
        $this->assertEqualsWithDelta($unit + $tax, (float) $items[0]['unit_price_after_tax'], 0.001);
        $this->assertEqualsWithDelta(5 * $unit, (float) $items[0]['line_total'], 0.001);
        $this->assertEqualsWithDelta(5 * ($unit + $tax), (float) $items[0]['line_total_after_tax'], 0.001);
    }

    public function test_unresolved_mismatch_ratio_does_not_apply(): void
    {
        $items = [[
            'quantity' => 10,
            'unit_price' => 1_000_000.0,
            'tax_per_unit' => 50_000.0,
            'vat_percent' => null,
            'line_total' => 9_999_999.0,
            'warnings' => [],
        ]];

        (new VatInferenceService)->apply($items);

        $this->assertArrayNotHasKey('unit_price_after_tax', $items[0]);
        $this->assertNull($items[0]['vat_percent'] ?? null);
        $this->assertEqualsWithDelta(9_999_999.0, (float) $items[0]['line_total'], 0.001);
    }

    public function test_does_not_infer_when_vat_percent_is_actual_percent_not_money(): void
    {
        $items = [[
            'quantity' => 2,
            'unit_price' => 100.0,
            'vat_percent' => 8.0,
            'tax_per_unit' => null,
            'warnings' => [],
        ]];

        (new VatInferenceService)->apply($items);

        $this->assertNull($items[0]['unit_price_after_tax'] ?? null);
    }

    public function test_treats_large_vat_percent_as_tax_per_unit_money(): void
    {
        $items = [[
            'quantity' => 63,
            'unit_price' => 4_150_000.0,
            'vat_percent' => 332_000.0,
            'tax_per_unit' => null,
            'warnings' => [],
        ]];

        (new VatInferenceService)->apply($items);

        $this->assertEqualsWithDelta(8.0, (float) $items[0]['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(332_000.0, (float) $items[0]['tax_per_unit'], 0.001);
    }
}
