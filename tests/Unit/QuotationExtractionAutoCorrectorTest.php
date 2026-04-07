<?php

namespace Tests\Unit;

use App\Services\AI\Correction\QuotationExtractionAutoCorrector;
use App\Services\AI\QuotationExtractionSchema;
use Tests\TestCase;

class QuotationExtractionAutoCorrectorTest extends TestCase
{
    public function test_clears_vat_percent_when_likely_currency_amount(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'vat_percent' => 332_000.0,
                'quantity' => 1.0,
                'unit_price' => 100.0,
                'line_total' => 100.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $this->assertNull($out['items'][0]['vat_percent']);
        $this->assertNotEmpty($out['extraction_meta']['auto_corrections']);
    }

    public function test_splits_merged_quantity_when_product_matches_line_total(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        // Digits "63415000" split as quantity 63 × unit_price 415000 = line_total
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63_415_000.0,
                'unit_price' => null,
                'line_total' => 63.0 * 415_000.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $this->assertEqualsWithDelta(63.0, (float) $out['items'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(415000.0, (float) $out['items'][0]['unit_price'], 0.001);
    }

    public function test_vat_per_unit_inference_when_vat_column_is_money_mislabeled_as_vat_percent(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63.0,
                'unit_price' => 4_150_000.0,
                'vat_percent' => 332_000.0,
                'line_total' => 282_366_000.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $item = $out['items'][0];
        $this->assertEqualsWithDelta(8.0, (float) $item['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(332_000.0, (float) $item['tax_per_unit'], 0.001);
        $this->assertEqualsWithDelta(4_482_000.0, (float) $item['unit_price_after_tax'], 0.001);
        $this->assertEqualsWithDelta(261_450_000.0, (float) $item['line_total_before_tax'], 0.001);
        $this->assertEqualsWithDelta(282_366_000.0, (float) $item['line_total_after_tax'], 0.001);
        $this->assertEqualsWithDelta(261_450_000.0, (float) $item['line_total'], 0.001);
        $this->assertTrue(collect($out['extraction_meta']['auto_corrections'])->contains(fn (array $c): bool => ($c['type'] ?? '') === 'vat_per_unit_inferred'));
        $this->assertContains('after_tax_total_detected', $out['items'][0]['warnings']);
        $this->assertTrue(collect($out['extraction_meta']['auto_corrections'])->contains(
            fn (array $c): bool => ($c['after_tax_total_verified'] ?? false) === true,
        ));
    }

    public function test_vat_per_unit_inference_from_explicit_tax_per_unit_field(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 10.0,
                'unit_price' => 1_000_000.0,
                'tax_per_unit' => 100_000.0,
                'vat_percent' => null,
                'line_total' => 11_000_000.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $item = $out['items'][0];
        $this->assertEqualsWithDelta(10.0, (float) $item['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(1_100_000.0, (float) $item['unit_price_after_tax'], 0.001);
        $this->assertEqualsWithDelta(10_000_000.0, (float) $item['line_total'], 0.001);
        $this->assertEqualsWithDelta(11_000_000.0, (float) $item['line_total_after_tax'], 0.001);
        $this->assertContains('after_tax_total_detected', $item['warnings']);
    }

    public function test_vat_inferred_from_warnings_when_vat_percent_null(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63.0,
                'unit_price' => 4_150_000.0,
                'vat_percent' => null,
                'line_total' => 282_366_000.0,
                'warnings' => [
                    'Thuế dòng (VNĐ): 332000',
                    'Cột đơn vị không rõ',
                ],
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $item = $out['items'][0];
        $this->assertEqualsWithDelta(8.0, (float) $item['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(332_000.0, (float) $item['tax_per_unit'], 0.001);
        $this->assertEqualsWithDelta(261_450_000.0, (float) $item['line_total'], 0.001);
        $this->assertEqualsWithDelta(282_366_000.0, (float) $item['line_total_after_tax'], 0.001);
        $this->assertTrue(collect($out['extraction_meta']['auto_corrections'])->contains(
            fn (array $c): bool => ($c['type'] ?? '') === 'vat_per_unit_inferred'
                && ($c['source'] ?? '') === 'warnings_vnd_per_unit',
        ));
        $this->assertContains('after_tax_total_detected', $item['warnings']);
    }

    public function test_warning_tax_when_extracted_line_total_not_after_tax_skips_reconciliation_flag(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63.0,
                'unit_price' => 4_150_000.0,
                'vat_percent' => null,
                'line_total' => 261_450_000.0,
                'warnings' => ['Thuế dòng (VNĐ): 332000'],
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $item = $out['items'][0];
        $this->assertEqualsWithDelta(8.0, (float) $item['vat_percent'], 0.001);
        $this->assertEqualsWithDelta(261_450_000.0, (float) $item['line_total'], 0.001);
        $this->assertEqualsWithDelta(282_366_000.0, (float) $item['line_total_after_tax'], 0.001);
        $this->assertNotContains('after_tax_total_detected', $item['warnings']);
    }

    public function test_warning_tax_scalars_only_when_line_total_missing(): void
    {
        config(['quotation_ai.auto_correct.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63.0,
                'unit_price' => 4_150_000.0,
                'vat_percent' => null,
                'line_total' => null,
                'warnings' => ['Thuế dòng (VNĐ): 332000'],
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionAutoCorrector::class)->apply($normalized);

        $item = $out['items'][0];
        $this->assertEqualsWithDelta(8.0, (float) $item['vat_percent'], 0.001);
        $this->assertNull($item['line_total']);
        $this->assertNull($item['line_total_after_tax']);
    }
}
