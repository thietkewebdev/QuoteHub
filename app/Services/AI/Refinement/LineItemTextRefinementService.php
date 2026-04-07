<?php

namespace App\Services\AI\Refinement;

use App\Services\AI\Prompting\VietnameseLineItemTextRefinementPromptBuilder;
use App\Services\AI\Support\ModelJsonContentDecoder;
use App\Support\Locale\ProductLineSpecsSplitter;
use App\Support\Locale\VietnameseTextSpacing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * After schema normalize: deterministic letter↔digit spacing + optional LLM Vietnamese word spacing for line text fields.
 */
final class LineItemTextRefinementService
{
    private const LLM_CHUNK_SIZE = 14;

    /** @var list<string> */
    private const LINE_TEXT_KEYS = ['raw_name', 'specs_text', 'warranty_text', 'origin_text'];

    /** @var array<string, mixed> */
    private array $lastMeta = ['applied' => false];

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function refineIfEnabled(array $normalized): array
    {
        $this->lastMeta = [
            'applied' => false,
            'regex_pass' => false,
            'glue_map_pass' => false,
            'llm_pass' => false,
        ];

        $items = $normalized['items'] ?? null;
        if (! is_array($items) || $items === []) {
            return $normalized;
        }

        $regexEnabled = (bool) config('quotation_ai.line_text_refinement.regex_letter_digit', true);
        $glueEnabled = (bool) config('quotation_ai.line_text_refinement.glue_phrases_enabled', true);
        $specsSplitEnabled = (bool) config('quotation_ai.line_text_refinement.product_specs_split', true);

        if ($regexEnabled || $glueEnabled) {
            $items = $this->applyRegexGlueToItems($items, $regexEnabled, $glueEnabled);
            $this->lastMeta['regex_pass'] = $regexEnabled;
            $this->lastMeta['glue_map_pass'] = $glueEnabled;
        }

        if ($specsSplitEnabled) {
            $minLen = (int) config('quotation_ai.line_text_refinement.product_specs_split_min_length', 80);
            foreach ($items as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                [$row['raw_name'], $row['specs_text']] = ProductLineSpecsSplitter::split(
                    (string) ($row['raw_name'] ?? ''),
                    (string) ($row['specs_text'] ?? ''),
                    max(40, $minLen),
                );
                $items[$i] = $row;
            }
        }

        $normalized['items'] = $items;

        $llmTried = false;
        if ((bool) config('quotation_ai.line_text_refinement.llm_enabled', true)
            && strtolower((string) config('quotation_ai.driver', 'openai')) !== 'mock') {
            $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));
            if ($apiKey !== '') {
                $llmTried = true;
                try {
                    $payload = $this->buildPayload($items);
                    if ($payload !== []) {
                        $builder = new VietnameseLineItemTextRefinementPromptBuilder;
                        $system = $builder->systemMessage();
                        $mergedByLine = [];

                        foreach (array_chunk($payload, self::LLM_CHUNK_SIZE) as $chunk) {
                            $user = $builder->userMessage($chunk);
                            $decoded = $this->callApi($system, $user);
                            $outItems = $decoded['items'] ?? null;
                            if (! is_array($outItems)) {
                                throw new RuntimeException('Line text refinement: missing items array.');
                            }
                            foreach ($outItems as $row) {
                                if (! is_array($row)) {
                                    continue;
                                }
                                $lineNo = (int) ($row['line_no'] ?? 0);
                                if ($lineNo < 1) {
                                    continue;
                                }
                                $mergedByLine[$lineNo] = [
                                    'raw_name' => is_string($row['raw_name'] ?? null) ? $row['raw_name'] : '',
                                    'specs_text' => is_string($row['specs_text'] ?? null) ? $row['specs_text'] : '',
                                    'warranty_text' => is_string($row['warranty_text'] ?? null) ? $row['warranty_text'] : '',
                                    'origin_text' => is_string($row['origin_text'] ?? null) ? $row['origin_text'] : '',
                                ];
                            }
                        }

                        foreach ($items as $i => $row) {
                            if (! is_array($row)) {
                                continue;
                            }
                            $lineNo = (int) ($row['line_no'] ?? ($i + 1));
                            if (! isset($mergedByLine[$lineNo])) {
                                continue;
                            }
                            $patch = $mergedByLine[$lineNo];
                            foreach (self::LINE_TEXT_KEYS as $key) {
                                if (array_key_exists($key, $patch)) {
                                    $row[$key] = $patch[$key];
                                }
                            }
                            $items[$i] = $row;
                        }

                        $normalized['items'] = $items;
                        $this->lastMeta['llm_pass'] = true;
                        $this->lastMeta['chunk_count'] = (int) ceil(count($payload) / self::LLM_CHUNK_SIZE);
                        $this->lastMeta['model'] = (string) config('quotation_ai.line_text_refinement.model', config('quotation_ai.openai.model', ''));
                    }
                } catch (Throwable $e) {
                    Log::warning('quotation_ai.line_text_refinement.failed', ['message' => $e->getMessage()]);
                    $this->lastMeta['error'] = $e->getMessage();
                }
            }
        }

        // Always re-apply deterministic spacing last so LLM/output quirks and Unicode variants are covered.
        if ($regexEnabled || $glueEnabled) {
            $normalized['items'] = $this->applyRegexGlueToItems(
                is_array($normalized['items']) ? $normalized['items'] : [],
                $regexEnabled,
                $glueEnabled
            );
        }

        $this->lastMeta['applied'] = $regexEnabled || $glueEnabled || ($this->lastMeta['llm_pass'] ?? false);
        if (! $llmTried) {
            $this->lastMeta['llm_pass'] = false;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function consumeLastMeta(): array
    {
        $m = $this->lastMeta;
        $this->lastMeta = ['applied' => false];

        return $m;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function applyRegexGlueToItems(array $items, bool $regexEnabled, bool $glueEnabled): array
    {
        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (self::LINE_TEXT_KEYS as $key) {
                if (! array_key_exists($key, $row)) {
                    continue;
                }
                $v = (string) ($row[$key] ?? '');
                if ($regexEnabled) {
                    $v = VietnameseTextSpacing::insertLetterDigitBoundaries($v);
                }
                if ($glueEnabled) {
                    $v = VietnameseTextSpacing::applyGluePhraseMap($v);
                }
                $row[$key] = $v;
            }
            $items[$i] = $row;
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{line_no: int, raw_name: string, specs_text: string, warranty_text: string, origin_text: string}>
     */
    private function buildPayload(array $items): array
    {
        $max = (int) config('quotation_ai.line_text_refinement.max_field_chars', 4000);
        $max = max(256, min(16_000, $max));
        $payload = [];

        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $lineNo = (int) ($row['line_no'] ?? ($i + 1));
            $payload[] = [
                'line_no' => $lineNo,
                'raw_name' => $this->clip((string) ($row['raw_name'] ?? ''), $max),
                'specs_text' => $this->clip((string) ($row['specs_text'] ?? ''), $max),
                'warranty_text' => $this->clip((string) ($row['warranty_text'] ?? ''), $max),
                'origin_text' => $this->clip((string) ($row['origin_text'] ?? ''), $max),
            ];
        }

        return $payload;
    }

    private function clip(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max);
    }

    /**
     * @return array<string, mixed>
     */
    private function callApi(string $system, string $user): array
    {
        $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));

        if ($apiKey === '') {
            throw new InvalidArgumentException('OpenAI API key is missing for line text refinement.');
        }

        $baseUrl = rtrim((string) config('quotation_ai.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) (config('quotation_ai.line_text_refinement.timeout') ?? config('quotation_ai.openai.timeout', 120));
        $modelConfig = config('quotation_ai.line_text_refinement.model');
        $model = (string) (filled($modelConfig) ? $modelConfig : config('quotation_ai.openai.model', 'gpt-4o-mini'));

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $temperature = config('quotation_ai.line_text_refinement.temperature');
        if ($temperature !== null && $temperature !== '') {
            $payload['temperature'] = (float) $temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI line text refinement failed (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $body = $response->json();
        if (isset($body['error']) && is_array($body['error'])) {
            throw new RuntimeException('OpenAI error: '.(string) ($body['error']['message'] ?? 'unknown'));
        }

        $content = data_get($body, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned empty line refinement content.');
        }

        return ModelJsonContentDecoder::decodeObject($content);
    }
}
