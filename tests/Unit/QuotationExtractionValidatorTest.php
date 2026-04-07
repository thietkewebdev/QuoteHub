<?php

namespace Tests\Unit;

use App\Services\AI\Correction\QuotationExtractionAutoCorrector;
use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\Validation\QuotationExtractionValidator;
use Tests\TestCase;

class QuotationExtractionValidatorTest extends TestCase
{
    public function test_flags_quantity_unit_price_line_total_mismatch(): void
    {
        config(['quotation_ai.validation.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 10.0,
                'unit_price' => 100.0,
                'line_total' => 50.0,
                'confidence_score' => 1.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionValidator::class)->apply($normalized);

        $this->assertStringContainsString('quantity × unit_price', implode(' ', $out['items'][0]['warnings']));
        $this->assertLessThan(1.0, (float) $out['items'][0]['confidence_score']);
        $this->assertGreaterThan(0, (int) ($out['extraction_meta']['validation_issue_count'] ?? 0));
    }

    public function test_sum_lines_mismatch_with_total_amount(): void
    {
        config(['quotation_ai.validation.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['quotation_header']['total_amount'] = 1_000_000.0;
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'line_total' => 100_000.0,
                'confidence_score' => 1.0,
            ]),
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'line_total' => 200_000.0,
                'confidence_score' => 1.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $out = app(QuotationExtractionValidator::class)->apply($normalized);

        $this->assertTrue(collect($out['document_warnings'])->contains(fn (string $w): bool => str_contains($w, 'total_amount')));
        $this->assertLessThan(1.0, (float) $out['overall_confidence']);
    }

    public function test_line_total_validates_against_unit_price_after_tax_when_present(): void
    {
        config(['quotation_ai.validation.enabled' => true]);

        $raw = QuotationExtractionSchema::template();
        $raw['items'] = [
            array_replace(QuotationExtractionSchema::itemTemplate(), [
                'quantity' => 63.0,
                'unit_price' => 4_150_000.0,
                'vat_percent' => 332_000.0,
                'line_total' => 282_366_000.0,
                'confidence_score' => 1.0,
            ]),
        ];
        $normalized = QuotationExtractionSchema::normalize($raw);
        $afterCorrect = app(QuotationExtractionAutoCorrector::class)->apply($normalized);
        $out = app(QuotationExtractionValidator::class)->apply($afterCorrect);

        $this->assertSame(0, (int) ($out['extraction_meta']['validation_issue_count'] ?? -1));
    }
}
