<?php

namespace App\Services\Quotation\HybridExtraction;

use App\Models\IngestionBatch;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;
use App\Services\AI\Support\ModelJsonContentDecoder;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * LLM pass on pre-segmented rows only (not full-document table extraction).
 */
final class LlmRowNormalizer
{
    /**
     * @param  list<RawTableRow>  $rows
     * @return array{quotation_header: array<string, mixed>, items: list<array<string, mixed>>, llm_document_warnings: list<string>}
     */
    public function normalize(
        string $ocrDocumentText,
        IngestionBatch $batch,
        array $rows,
        ?SupplierExtractionContext $supplierContext,
    ): array {
        $driver = strtolower((string) config('quotation_ai.driver', 'openai'));

        if ($driver === 'mock' || ! (bool) config('quotation_ai.hybrid.llm_row_normalizer_enabled', true)) {
            return $this->heuristicNormalize($ocrDocumentText, $rows);
        }

        return $this->callOpenAi($ocrDocumentText, $batch, $rows, $supplierContext);
    }

    /**
     * @param  list<RawTableRow>  $rows
     * @return array{quotation_header: array<string, mixed>, items: list<array<string, mixed>>, llm_document_warnings: list<string>}
     */
    private function heuristicNormalize(string $ocrDocumentText, array $rows): array
    {
        $items = [];
        foreach ($rows as $i => $row) {
            $parsed = $this->parseNumbersFromCells($row->cells);
            if ($parsed === null) {
                continue;
            }
            [$name, $quantity, $unitPrice, $maybeVatOrTax, $lineTotal] = $parsed;
            $items[] = [
                'line_no' => $i + 1,
                'raw_name' => $name,
                'raw_model' => '',
                'brand' => '',
                'unit' => '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_percent' => $maybeVatOrTax !== null && $maybeVatOrTax <= 100 ? $maybeVatOrTax : null,
                'tax_per_unit' => $maybeVatOrTax !== null && $maybeVatOrTax > 100 ? $maybeVatOrTax : null,
                'unit_price_after_tax' => null,
                'line_total' => $lineTotal,
                'line_total_before_tax' => null,
                'line_total_after_tax' => null,
                'warranty_text' => '',
                'origin_text' => '',
                'specs_text' => '',
                'confidence_score' => 0.35,
                'field_confidence' => [],
                'warnings' => ['hybrid_heuristic_row: mock/heuristic parse — verify all fields.'],
            ];
        }

        return [
            'quotation_header' => $this->blankHeader(),
            'items' => $items,
            'llm_document_warnings' => $items === []
                ? ['hybrid: no rows parsed heuristically; enable OpenAI driver for LLM row normalization.']
                : [],
        ];
    }

    /**
     * @param  list<RawTableRow>  $rows
     * @return array{quotation_header: array<string, mixed>, items: list<array<string, mixed>>, llm_document_warnings: list<string>}
     */
    private function callOpenAi(
        string $ocrDocumentText,
        IngestionBatch $batch,
        array $rows,
        ?SupplierExtractionContext $supplierContext,
    ): array {
        $apiKey = trim((string) (config('quotation_ai.openai.api_key') ?: config('services.openai.api_key') ?: ''));
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                __('OpenAI API key is missing for hybrid row normalizer.')
            );
        }

        $payloadRows = [];
        foreach ($rows as $row) {
            $payloadRows[] = [
                'line_index' => $row->lineIndex,
                'raw_line' => $row->rawLine,
                'cells' => $row->cells,
            ];
        }

        $snippet = mb_substr($ocrDocumentText, 0, (int) config('quotation_ai.hybrid.header_snippet_max_chars', 4000));
        $profile = $supplierContext === null ? 'none' : $supplierContext->mode->value;

        $system = <<<'SYS'
You normalize Vietnamese supplier quotation LINE ITEMS only. Input is JSON with header_snippet (OCR text) and table rows (cells from OCR). Do NOT invent prices. Map cells to quantity, unit_price (before tax), tax_per_unit (VND per unit if present), vat_percent (if clearly a percent), line_total (pre-tax row total if visible), raw_name (product text), raw_model, brand, unit, specs_text. Use JSON numbers. If unsure, null. Output a single JSON object: {"quotation_header":{...},"items":[...],"document_warnings":[]}. quotation_header: supplier_name, supplier_quote_number, quote_date (ISO or empty), currency (VND), total_amount, notes, contact_person — only if visible in header_snippet.
SYS;

        $user = json_encode([
            'batch_id' => $batch->getKey(),
            'supplier_profile_mode' => $profile,
            'header_snippet' => $snippet,
            'rows' => $payloadRows,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $baseUrl = rtrim((string) config('quotation_ai.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = (int) config('quotation_ai.openai.timeout', 120);
        $model = (string) config('quotation_ai.hybrid.row_normalizer_model', config('quotation_ai.openai.model', 'gpt-4o'));

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
        ];
        $temperature = config('quotation_ai.openai.temperature');
        if ($temperature !== null) {
            $body['temperature'] = (float) $temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $body);

        if ($response->failed()) {
            throw new RuntimeException('Hybrid row normalizer HTTP '.$response->status().': '.$response->body());
        }

        $json = $response->json();
        if (isset($json['error']) && is_array($json['error'])) {
            throw new RuntimeException('OpenAI error: '.(string) ($json['error']['message'] ?? 'unknown'));
        }

        $content = data_get($json, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Hybrid row normalizer returned empty content.');
        }

        $decoded = ModelJsonContentDecoder::decodeObject($content);
        $header = is_array($decoded['quotation_header'] ?? null) ? $decoded['quotation_header'] : $this->blankHeader();
        $items = is_array($decoded['items'] ?? null) ? array_values($decoded['items']) : [];
        $warn = $decoded['document_warnings'] ?? [];
        $docWarn = is_array($warn) ? array_values(array_map(fn ($w) => (string) $w, $warn)) : [];

        return [
            'quotation_header' => $header,
            'items' => $items,
            'llm_document_warnings' => $docWarn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankHeader(): array
    {
        return [
            'supplier_name' => '',
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
        ];
    }

    /**
     * @param  list<string>  $cells
     * @return array{0: string, 1: float, 2: float, 3: float|null, 4: float}|null
     */
    private function parseNumbersFromCells(array $cells): ?array
    {
        $textParts = [];
        $nums = [];
        foreach ($cells as $c) {
            $c = trim($c);
            if ($c === '') {
                continue;
            }
            $n = $this->parseVnNumber($c);
            if ($n !== null) {
                $nums[] = $n;
            } else {
                $textParts[] = $c;
            }
        }

        if (count($nums) < 2) {
            return null;
        }

        $name = trim(implode(' ', $textParts));
        if ($name === '') {
            $name = '—';
        }

        if (count($nums) >= 4) {
            $quantity = $nums[count($nums) - 4];
            $unitPrice = $nums[count($nums) - 3];
            $third = $nums[count($nums) - 2];
            $lineTotal = $nums[count($nums) - 1];

            return [$name, $quantity, $unitPrice, $third, $lineTotal];
        }

        $quantity = $nums[count($nums) - 2];
        $unitPrice = $nums[count($nums) - 1];
        $lineTotal = $quantity * $unitPrice;

        return [$name, $quantity, $unitPrice, null, $lineTotal];
    }

    private function parseVnNumber(string $s): ?float
    {
        $t = str_replace([' ', "\u{00A0}"], '', $s);
        if ($t === '' || ! preg_match('/^\d[\d\.,]*$/', $t)) {
            return null;
        }
        $t = str_replace('.', '', $t);
        $t = str_replace(',', '.', $t);

        return is_numeric($t) ? (float) $t : null;
    }
}
