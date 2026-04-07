<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * Heuristic table-row detection from OCR lines (parser-first; no LLM).
 */
final class TableRowAssembler
{
    /**
     * @param  list<string>  $lines
     * @return list<RawTableRow>
     */
    public function assemble(array $lines): array
    {
        $rows = [];
        $inTable = false;

        foreach (array_values($lines) as $idx => $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }

            if ($this->looksLikeTableHeader($trim)) {
                $inTable = true;

                continue;
            }

            if (! $inTable && $this->numericDensity($trim) < 0.25) {
                continue;
            }

            if (! $this->looksLikeDataRow($trim)) {
                if ($inTable && $this->numericDensity($trim) < 0.15) {
                    $inTable = false;
                }

                continue;
            }

            $cells = $this->splitCells($trim);
            if (count($cells) < 2) {
                continue;
            }

            $rows[] = new RawTableRow(lineIndex: $idx, rawLine: $trim, cells: $cells);
        }

        return $rows;
    }

    private function looksLikeTableHeader(string $line): bool
    {
        $l = mb_strtolower($line);

        return (bool) preg_match(
            '/\b(stt|tt|tên\s*hàng|tên\s*sản\s*phẩm|đơn\s*giá|thành\s*tiền|số\s*lượng|sl|vat|thuế)\b/iu',
            $l
        );
    }

    private function looksLikeDataRow(string $line): bool
    {
        $numericTokens = preg_match_all('/\d[\d\.,]*/u', $line) ?: 0;

        return $numericTokens >= 2 && mb_strlen($line) >= 8;
    }

    private function numericDensity(string $line): float
    {
        $len = mb_strlen($line);
        if ($len < 1) {
            return 0.0;
        }
        $digits = preg_match_all('/\d/u', $line) ?: 0;

        return $digits / $len;
    }

    /**
     * @return list<string>
     */
    private function splitCells(string $line): array
    {
        if (str_contains($line, "\t")) {
            $parts = array_map('trim', explode("\t", $line));

            return array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
        }

        $parts = preg_split('/\s{2,}|\s*\|\s*/u', $line) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $p): bool => $p !== ''));
    }
}
