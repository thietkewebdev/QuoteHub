<?php

namespace App\Services\AI\Ocr;

use App\Services\AI\Prompting\VietnameseOcrRefinementPromptBuilder;
use App\Services\AI\Support\ModelJsonContentDecoder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Optional OpenAI pass: improve spacing and line breaks in compiled OCR before extraction.
 */
final class OcrDocumentRefinementService
{
    /** @var array<string, mixed> */
    private array $lastMeta = ['applied' => false];

    /**
     * @return array<string, mixed>
     */
    public function consumeLastMeta(): array
    {
        $m = $this->lastMeta;
        $this->lastMeta = ['applied' => false];

        return $m;
    }

    public function refineIfEnabled(string $compiledOcr): string
    {
        $this->lastMeta = ['applied' => false];

        if (! (bool) config('quotation_ai.ocr_refinement.enabled', true)) {
            return $compiledOcr;
        }

        if (strtolower((string) config('quotation_ai.driver', 'openai')) === 'mock') {
            return $compiledOcr;
        }

        $compiledOcr = trim($compiledOcr);
        if ($compiledOcr === '') {
            return $compiledOcr;
        }

        $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));
        if ($apiKey === '') {
            return $compiledOcr;
        }

        $maxChars = max(2000, (int) config('quotation_ai.ocr_refinement.max_chars_per_chunk', 12_000));
        $chunks = $this->splitIntoChunks($compiledOcr, $maxChars);
        $total = count($chunks);

        try {
            $builder = new VietnameseOcrRefinementPromptBuilder;
            $system = $builder->systemMessage();
            $refinedParts = [];

            foreach ($chunks as $i => $chunk) {
                $idx = $i + 1;
                $user = $builder->userMessage($chunk, $total > 1, $idx, $total);
                $text = $this->callRefinementApi($system, $user);
                $refinedParts[] = trim($text);
            }

            $merged = trim(implode("\n\n", $refinedParts));

            if ($merged === '') {
                $this->markSkipped('empty_output');

                return $compiledOcr;
            }

            if (strlen($merged) < (int) (strlen($compiledOcr) * 0.25)) {
                Log::warning('quotation_ai.ocr_refinement.discarded_short', [
                    'original_len' => strlen($compiledOcr),
                    'refined_len' => strlen($merged),
                ]);
                $this->markSkipped('output_too_short');

                return $compiledOcr;
            }

            $this->lastMeta = [
                'applied' => true,
                'chunk_count' => $total,
                'model' => (string) config('quotation_ai.ocr_refinement.model', config('quotation_ai.openai.model', '')),
            ];

            return $merged;
        } catch (Throwable $e) {
            Log::warning('quotation_ai.ocr_refinement.failed', [
                'message' => $e->getMessage(),
            ]);
            $this->lastMeta = [
                'applied' => false,
                'error' => $e->getMessage(),
            ];

            return $compiledOcr;
        }
    }

    private function markSkipped(string $reason): void
    {
        $this->lastMeta = ['applied' => false, 'skipped' => $reason];
    }

    /**
     * @return list<string>
     */
    private function splitIntoChunks(string $document, int $maxChars): array
    {
        if (strlen($document) <= $maxChars) {
            return [$document];
        }

        $sections = preg_split('/\n\n(?==== )/', $document, -1, PREG_SPLIT_NO_EMPTY);
        if ($sections === false || $sections === []) {
            return $this->splitByLength($document, $maxChars);
        }

        $chunks = [];
        $buffer = '';

        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '') {
                continue;
            }

            if (strlen($buffer) + strlen($section) + 2 <= $maxChars) {
                $buffer = $buffer === '' ? $section : $buffer."\n\n".$section;

                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
                $buffer = '';
            }

            if (strlen($section) <= $maxChars) {
                $buffer = $section;

                continue;
            }

            foreach ($this->splitByLength($section, $maxChars) as $piece) {
                $chunks[] = $piece;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks !== [] ? $chunks : [$document];
    }

    /**
     * @return list<string>
     */
    private function splitByLength(string $text, int $maxChars): array
    {
        $out = [];
        $len = strlen($text);
        $offset = 0;

        while ($offset < $len) {
            $out[] = substr($text, $offset, $maxChars);
            $offset += $maxChars;
        }

        return $out;
    }

    private function callRefinementApi(string $system, string $user): string
    {
        $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));

        if ($apiKey === '') {
            throw new InvalidArgumentException('OpenAI API key is missing for OCR refinement.');
        }

        $baseUrl = rtrim((string) config('quotation_ai.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) (config('quotation_ai.ocr_refinement.timeout') ?? config('quotation_ai.openai.timeout', 120));
        $modelConfig = config('quotation_ai.ocr_refinement.model');
        $model = (string) (filled($modelConfig) ? $modelConfig : config('quotation_ai.openai.model', 'gpt-4o-mini'));

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $temperature = config('quotation_ai.ocr_refinement.temperature');
        if ($temperature !== null && $temperature !== '') {
            $payload['temperature'] = (float) $temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI OCR refinement failed (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $body = $response->json();
        if (isset($body['error']) && is_array($body['error'])) {
            throw new RuntimeException('OpenAI error: '.(string) ($body['error']['message'] ?? 'unknown'));
        }

        $content = data_get($body, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned empty refinement content.');
        }

        $decoded = ModelJsonContentDecoder::decodeObject($content);
        $doc = $decoded['refined_document'] ?? null;

        if (! is_string($doc)) {
            throw new RuntimeException('OpenAI refinement JSON missing refined_document string.');
        }

        return $doc;
    }
}
