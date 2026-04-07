<?php

namespace Tests\Feature;

use App\Models\IngestionBatch;
use App\Services\AI\Providers\OpenAiQuotationExtractionProvider;
use App\Services\AI\QuotationExtractionSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiQuotationExtractionProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_two_pass_merges_header_and_items_from_two_completions(): void
    {
        config([
            'quotation_ai.openai.api_key' => 'sk-test',
            'quotation_ai.openai.base_url' => 'https://api.openai.com/v1',
            'quotation_ai.extraction_engine.version' => 'v2',
        ]);

        $header = QuotationExtractionSchema::template()['quotation_header'];
        $header['supplier_name'] = 'ACME VN';
        $header['supplier_quote_number'] = 'BG-01';
        $header['quote_date'] = '2026-01-15';
        $header['total_amount'] = 1_000_000;

        $pass1 = [
            'quotation_header' => $header,
            'document_warnings' => [],
            'overall_confidence' => 0.9,
        ];

        $pass2 = [
            'items' => [],
            'document_warnings' => [],
            'overall_confidence' => 0.95,
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode($pass1)]]]], 200)
                ->push(['choices' => [['message' => ['content' => json_encode($pass2)]]]], 200),
        ]);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'ocr_done',
        ]);
        $extractor = app(OpenAiQuotationExtractionProvider::class);
        $out = $extractor->extract("BÁO GIÁ\nACME VN", $batch);

        $this->assertSame('ACME VN', $out['quotation_header']['supplier_name']);
        $this->assertEqualsWithDelta(1_000_000.0, (float) $out['quotation_header']['total_amount'], 0.001);
        $this->assertSame('v2-two-pass', $out['extraction_meta']['engine_version']);
        $this->assertSame(2, $out['extraction_meta']['pass_count']);
        Http::assertSentCount(2);
    }
}
