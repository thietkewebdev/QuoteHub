<?php

namespace Tests\Unit;

use App\Support\Quotation\QuotationLinePresentation;
use PHPUnit\Framework\TestCase;

class QuotationLinePresentationTest extends TestCase
{
    public function test_line_total_including_vat_multiplies_when_vat_set(): void
    {
        $out = QuotationLinePresentation::lineTotalIncludingVat(261_450_000, 8);

        $this->assertEqualsWithDelta(282_366_000.0, $out, 0.001);
    }

    public function test_line_total_including_vat_returns_excl_when_vat_missing(): void
    {
        $out = QuotationLinePresentation::lineTotalIncludingVat(100, null);

        $this->assertEqualsWithDelta(100.0, $out, 0.001);
    }

    public function test_line_total_including_vat_null_when_line_blank(): void
    {
        $this->assertNull(QuotationLinePresentation::lineTotalIncludingVat(null, 10));
    }

    public function test_line_vat_amount_is_percent_of_excl_total(): void
    {
        $out = QuotationLinePresentation::lineVatAmount(261_450_000, 8);

        $this->assertEqualsWithDelta(20_916_000.0, $out, 0.001);
    }

    public function test_line_vat_amount_null_when_vat_percent_missing(): void
    {
        $this->assertNull(QuotationLinePresentation::lineVatAmount(100, null));
    }

    public function test_line_vat_amount_zero_when_vat_percent_is_zero(): void
    {
        $out = QuotationLinePresentation::lineVatAmount(100, 0);

        $this->assertEqualsWithDelta(0.0, $out, 0.001);
    }
}
