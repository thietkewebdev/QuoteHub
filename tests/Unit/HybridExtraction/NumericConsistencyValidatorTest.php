<?php

namespace Tests\Unit\HybridExtraction;

use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\Validation\QuotationExtractionValidator;
use App\Services\Quotation\HybridExtraction\NumericConsistencyValidator;
use Tests\TestCase;

class NumericConsistencyValidatorTest extends TestCase
{
    public function test_adds_warning_when_quantity_times_price_mismatches_line_total(): void
    {
        $normalized = QuotationExtractionSchema::normalize([
            'quotation_header' => [
                'supplier_name' => 'X',
                'supplier_quote_number' => '',
                'quote_date' => '',
                'valid_until' => '',
                'currency' => 'VND',
                'subtotal_before_tax' => null,
                'tax_amount' => null,
                'total_amount' => null,
                'contact_person' => '',
                'notes' => '',
                'field_confidence' => [],
            ],
            'items' => [[
                'raw_name' => 'A',
                'raw_model' => '',
                'brand' => '',
                'unit' => '',
                'quantity' => 10,
                'unit_price' => 1000,
                'vat_percent' => null,
                'line_total' => 50_000,
                'specs_text' => '',
                'warnings' => [],
                'confidence_score' => 0.9,
                'field_confidence' => [],
            ]],
            'document_warnings' => [],
            'overall_confidence' => 0.9,
            'extraction_meta' => [
                'engine_version' => 'test',
                'pass_count' => 1,
            ],
        ]);

        $validator = new NumericConsistencyValidator(new QuotationExtractionValidator);
        $out = $validator->apply($normalized);

        $warnings = $out['items'][0]['warnings'] ?? [];
        $this->assertNotEmpty($warnings);
        $joined = implode(' ', $warnings);
        $this->assertStringContainsString('không khớp', $joined);
    }
}
