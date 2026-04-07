<?php

namespace App\Services\AI\Providers;

use App\Models\IngestionBatch;
use App\Services\AI\Contracts\QuotationExtractionProviderInterface;
use App\Services\AI\Prompting\VietnameseQuotationPass1PromptBuilder;
use App\Services\AI\Prompting\VietnameseQuotationPass2PromptBuilder;
use App\Services\AI\Prompting\VietnameseQuotationPromptBuilder;
use App\Services\AI\Segmentation\OcrTableRegionSegmenter;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;
use App\Services\AI\Support\ModelJsonContentDecoder;
use App\Support\SupplierExtraction\SupplierProfileApplicationMode;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class OpenAiQuotationExtractionProvider implements QuotationExtractionProviderInterface
{
    public function modelLabel(): string
    {
        return (string) config('quotation_ai.openai.model', 'gpt-4o');
    }

    public function extract(string $ocrDocumentText, IngestionBatch $batch, ?SupplierExtractionContext $supplierContext = null): array
    {
        $supplierContext ??= new SupplierExtractionContext(
            mode: SupplierProfileApplicationMode::None,
            supplierId: null,
            profile: null,
            inferenceRawScore: null,
            supplierInferenceConfidence: null,
            matchedTerms: [],
        );

        $engine = strtolower((string) config('quotation_ai.extraction_engine.version', 'v2'));

        return $engine === 'v1'
            ? $this->extractSinglePass($ocrDocumentText, $batch, $supplierContext)
            : $this->extractTwoPass($ocrDocumentText, $batch, $supplierContext);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSinglePass(string $ocrDocumentText, IngestionBatch $batch, SupplierExtractionContext $supplierContext): array
    {
        $legacy = new VietnameseQuotationPromptBuilder;
        $system = $legacy->systemMessage();
        $user = $legacy->userMessage($ocrDocumentText, $batch, $supplierContext);
        $raw = $this->callChatCompletions($system, $user);
        $meta = is_array($raw['extraction_meta'] ?? null) ? $raw['extraction_meta'] : [];
        $raw['extraction_meta'] = array_merge($meta, [
            'engine_version' => 'v1-single-pass',
            'pass_count' => 1,
        ]);

        return $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTwoPass(string $ocrDocumentText, IngestionBatch $batch, SupplierExtractionContext $supplierContext): array
    {
        $pass1Builder = new VietnameseQuotationPass1PromptBuilder;
        $pass1Raw = $this->callChatCompletions(
            $pass1Builder->systemMessage(),
            $pass1Builder->userMessage($ocrDocumentText, $batch, $supplierContext),
        );
        $header = is_array($pass1Raw['quotation_header'] ?? null) ? $pass1Raw['quotation_header'] : [];

        $segmenter = new OcrTableRegionSegmenter;
        [$lineItemOcr, $tableSegMeta] = $segmenter->extractLineItemRegion($ocrDocumentText);

        $pass2Builder = new VietnameseQuotationPass2PromptBuilder;
        $pass2Raw = $this->callChatCompletions(
            $pass2Builder->systemMessage(),
            $pass2Builder->userMessage($batch, $supplierContext, $header, $lineItemOcr, $tableSegMeta),
        );

        return $this->mergeTwoPasses($pass1Raw, $pass2Raw, $tableSegMeta);
    }

    /**
     * @param  array<string, mixed>  $pass1
     * @param  array<string, mixed>  $pass2
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $tableSegMeta
     */
    private function mergeTwoPasses(array $pass1, array $pass2, array $tableSegMeta): array
    {
        $quotationHeader = is_array($pass1['quotation_header'] ?? null) ? $pass1['quotation_header'] : [];
        $items = is_array($pass2['items'] ?? null) ? $pass2['items'] : [];

        $w1 = is_array($pass1['document_warnings'] ?? null) ? $pass1['document_warnings'] : [];
        $w2 = is_array($pass2['document_warnings'] ?? null) ? $pass2['document_warnings'] : [];
        $mergedWarnings = array_values(array_unique(array_merge(
            array_map(fn ($w) => (string) $w, $w1),
            array_map(fn ($w) => (string) $w, $w2),
        )));

        $c1 = $this->floatOrZero($pass1['overall_confidence'] ?? 0.0);
        $c2 = $this->floatOrZero($pass2['overall_confidence'] ?? 0.0);
        $wp1 = (float) config('quotation_ai.extraction_engine.pass1_confidence_weight', 0.4);
        $wp2 = (float) config('quotation_ai.extraction_engine.pass2_confidence_weight', 0.6);
        $wsum = $wp1 + $wp2;
        $overall = $wsum > 0.0 ? ($wp1 * $c1 + $wp2 * $c2) / $wsum : min($c1, $c2);

        return [
            'quotation_header' => $quotationHeader,
            'items' => $items,
            'document_warnings' => $mergedWarnings,
            'overall_confidence' => $overall,
            'extraction_meta' => [
                'engine_version' => 'v2-two-pass',
                'pass_count' => 2,
                'pass1_confidence' => $c1,
                'pass2_confidence' => $c2,
                'overall_confidence_weighted' => $overall,
                'pass_confidence_weights' => ['pass1' => $wp1, 'pass2' => $wp2],
                'table_segmentation' => $tableSegMeta,
            ],
        ];
    }

    private function floatOrZero(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function callChatCompletions(string $system, string $user): array
    {
        $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                __('OpenAI API key is missing. Set OPENAI_API_KEY, or use QUOTATION_AI_DRIVER=mock for local testing.')
            );
        }

        $baseUrl = rtrim((string) config('quotation_ai.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) config('quotation_ai.openai.timeout', 120);

        $payload = [
            'model' => config('quotation_ai.openai.model', 'gpt-4o'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $temperature = config('quotation_ai.openai.temperature');
        if ($temperature !== null) {
            $payload['temperature'] = (float) $temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI request failed (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $body = $response->json();
        if (isset($body['error']) && is_array($body['error'])) {
            $msg = (string) ($body['error']['message'] ?? 'Unknown OpenAI error');

            throw new RuntimeException('OpenAI error: '.$msg);
        }

        $content = data_get($body, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned an empty message content.');
        }

        return ModelJsonContentDecoder::decodeObject($content);
    }
}
