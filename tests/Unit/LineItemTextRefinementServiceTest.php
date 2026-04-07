<?php

namespace Tests\Unit;

use App\Services\AI\QuotationExtractionSchema;
use App\Services\AI\Refinement\LineItemTextRefinementService;
use Tests\TestCase;

class LineItemTextRefinementServiceTest extends TestCase
{
    public function test_regex_only_when_llm_disabled(): void
    {
        config([
            'quotation_ai.line_text_refinement.regex_letter_digit' => true,
            'quotation_ai.line_text_refinement.llm_enabled' => false,
        ]);

        $normalized = [
            'items' => [
                array_replace(QuotationExtractionSchema::itemTemplate(), [
                    'line_no' => 1,
                    'raw_name' => 'Máy in mãvạch giải300dpi',
                    'specs_text' => 'tốiđa100mm',
                ]),
            ],
            'extraction_meta' => ['engine_version' => 'test', 'pass_count' => 2],
        ];

        $service = new LineItemTextRefinementService;
        $out = $service->refineIfEnabled($normalized);

        $this->assertStringContainsString('300 dpi', $out['items'][0]['raw_name']);
        $meta = $service->consumeLastMeta();
        $this->assertTrue($meta['regex_pass']);
        $this->assertTrue($meta['glue_map_pass']);
        $this->assertFalse($meta['llm_pass']);
    }
}
