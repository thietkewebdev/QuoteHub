<?php

namespace Tests\Unit;

use App\Services\AI\Ocr\OcrDocumentRefinementService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OcrDocumentRefinementServiceTest extends TestCase
{
    public function test_skips_when_disabled(): void
    {
        config(['quotation_ai.ocr_refinement.enabled' => false]);

        $service = new OcrDocumentRefinementService;
        $raw = 'CôngtyABCĐơngiá1000';

        $this->assertSame($raw, $service->refineIfEnabled($raw));
        $this->assertSame(['applied' => false], $service->consumeLastMeta());
    }

    public function test_calls_openai_when_enabled_and_merges_meta(): void
    {
        config([
            'quotation_ai.ocr_refinement.enabled' => true,
            'quotation_ai.driver' => 'openai',
            'quotation_ai.openai.api_key' => 'sk-test',
            'quotation_ai.openai.base_url' => 'https://api.openai.com/v1',
            'quotation_ai.openai.model' => 'gpt-test',
            'quotation_ai.ocr_refinement.model' => null,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(['refined_document' => "Công ty ABC\nĐơn giá 1000"], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OcrDocumentRefinementService;
        $out = $service->refineIfEnabled('CôngtyABCĐơngiá1000');

        $this->assertStringContainsString('Công ty ABC', $out);
        $meta = $service->consumeLastMeta();
        $this->assertTrue($meta['applied']);
        $this->assertSame(1, $meta['chunk_count']);
    }
}
