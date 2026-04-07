<?php

namespace App\Services\QuotationExtraction;

/**
 * Maps raw OCR payloads (Document AI / Vision shape) to raw quotation line rows without LLM.
 */
final class RawQuotationExtractionService
{
    public function __construct(
        private readonly TableRowAssembler $assembler,
    ) {}

    /**
     * @param  array{
     *     provider?: string,
     *     full_text?: string,
     *     pages?: list<array<string, mixed>>
     * }  $ocrPayload
     * @return array{rows: list<array<string, string>>, warnings: list<string>}
     */
    public function extract(array $ocrPayload): array
    {
        $warnings = [];
        $pages = $ocrPayload['pages'] ?? [];
        if (! is_array($pages)) {
            $pages = [];
        }

        $tableMatrices = $this->collectTableMatrices($pages);
        $rows = [];

        if ($tableMatrices !== []) {
            $warnings[] = __('Using Document AI-style tables from OCR pages.');
            foreach ($tableMatrices as $index => $matrix) {
                $result = $this->assembler->mapTableMatrix($matrix);
                foreach ($result['warnings'] as $w) {
                    $warnings[] = __('Table :n: :msg', ['n' => (string) ($index + 1), 'msg' => $w]);
                }
                $rows = array_merge($rows, $result['rows']);
            }
        }

        if ($rows === []) {
            $warnings[] = __('No usable table matrices; falling back to block/text lines.');
            $lines = $this->linesFromOcrPayload($ocrPayload, $pages);
            $result = $this->assembler->assembleFromLines($lines);
            foreach ($result['warnings'] as $w) {
                $warnings[] = $w;
            }
            $rows = $result['rows'];
        }

        return ['rows' => $rows, 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<list<list<string>>>
     */
    private function collectTableMatrices(array $pages): array
    {
        $matrices = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($page['tables'] ?? [] as $table) {
                if (! is_array($table)) {
                    continue;
                }
                $rows = $table['rows'] ?? null;
                if (! is_array($rows) || $rows === []) {
                    continue;
                }
                if ($this->matrixHasContent($rows)) {
                    $matrices[] = $rows;
                }
            }
        }

        return $matrices;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function matrixHasContent(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<string>
     */
    private function linesFromOcrPayload(array $ocrPayload, array $pages): array
    {
        $lines = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($page['blocks'] ?? [] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (! empty($block['paragraphs']) && is_array($block['paragraphs'])) {
                    foreach ($block['paragraphs'] as $paragraph) {
                        if (! is_array($paragraph)) {
                            continue;
                        }
                        $t = trim((string) ($paragraph['text'] ?? ''));
                        if ($t !== '') {
                            $lines = array_merge($lines, $this->splitIntoLines($t));
                        }
                    }
                } else {
                    $t = trim((string) ($block['text'] ?? ''));
                    if ($t !== '') {
                        $lines = array_merge($lines, $this->splitIntoLines($t));
                    }
                }
            }
        }

        $lines = array_values(array_filter(array_map(trim(...), $lines), fn (string $l): bool => $l !== ''));

        if ($lines !== []) {
            return $lines;
        }

        $full = trim((string) ($ocrPayload['full_text'] ?? ''));

        return $full === '' ? [] : array_values(array_filter(array_map(trim(...), $this->splitIntoLines($full)), fn (string $l): bool => $l !== ''));
    }

    /**
     * @return list<string>
     */
    private function splitIntoLines(string $text): array
    {
        $parts = preg_split('/\R+/u', $text) ?: [];

        return array_values(array_filter($parts, fn (string $p): bool => trim($p) !== ''));
    }
}
