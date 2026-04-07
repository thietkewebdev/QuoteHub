<?php

namespace Tests\Unit;

use App\Services\Quotation\ManualQuotationPayloadEnricher;
use Tests\TestCase;

class ManualQuotationPayloadEnricherTest extends TestCase
{
    public function test_fills_line_total_and_total_from_quantity_and_prices(): void
    {
        $enricher = new ManualQuotationPayloadEnricher;
        $out = $enricher->enrich([
            'items' => [
                ['quantity' => 2, 'unit_price' => 1000, 'line_total' => null],
                ['quantity' => 1, 'unit_price' => 500, 'line_total' => ''],
            ],
            'total_amount' => null,
        ]);

        $this->assertEqualsWithDelta(2000.0, (float) $out['items'][0]['line_total'], 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $out['items'][1]['line_total'], 0.001);
        $this->assertEqualsWithDelta(2500.0, (float) $out['total_amount'], 0.001);
    }

    public function test_respects_explicit_total_amount(): void
    {
        $enricher = new ManualQuotationPayloadEnricher;
        $out = $enricher->enrich([
            'items' => [
                ['quantity' => 1, 'unit_price' => 10, 'line_total' => null],
            ],
            'total_amount' => 99,
        ]);

        $this->assertEqualsWithDelta(99.0, (float) $out['total_amount'], 0.001);
    }
}
