<?php

namespace App\Services\QuotationExtraction;

/**
 * Maps OCR table matrices or text lines to raw quotation row field shapes (no LLM).
 * When column semantics are uncertain, values are kept in raw_description and warnings are recorded.
 */
final class TableRowAssembler
{
    private const OUTPUT_KEYS = [
        'raw_description',
        'raw_qty',
        'raw_unit_price',
        'raw_tax_amount',
        'raw_line_total',
    ];

    /**
     * @param  list<list<string>>  $matrix  rows of cells (trimmed externally optional)
     * @return array{rows: list<array<string, string>>, warnings: list<string>}
     */
    public function mapTableMatrix(array $matrix): array
    {
        $warnings = [];
        $matrix = $this->normalizeMatrix($matrix);
        if ($matrix === []) {
            return ['rows' => [], 'warnings' => []];
        }

        $headerAnalysis = $this->analyzeHeaderRow($matrix[0]);
        $dataRows = $matrix;
        $roles = null;
        $useTrustedRoles = false;

        foreach ($headerAnalysis['warnings'] as $w) {
            $warnings[] = $w;
        }

        if ($headerAnalysis['is_header'] && $headerAnalysis['mapping_trusted']) {
            $roles = $headerAnalysis['roles'];
            $useTrustedRoles = true;
            array_shift($dataRows);
        } elseif ($headerAnalysis['is_header']) {
            $warnings[] = __('Table header did not yield a trusted column map; header row skipped, body rows store joined cells in raw_description.');
            array_shift($dataRows);
        } else {
            $warnings[] = __('No clear table header row; treating all rows as unparsed tabular lines.');
        }

        $rows = [];
        foreach ($dataRows as $row) {
            $row = $this->trimRow($row);
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            if ($this->looksLikeFooterRow($row)) {
                $warnings[] = __('Skipped a row that looks like a totals/footer line.');

                continue;
            }
            if ($useTrustedRoles && $roles !== null) {
                $rows[] = $this->mapRowWithRoles($row, $roles, $warnings);
            } else {
                $rows[] = $this->unparsedRowFromCells($row);
            }
        }

        return ['rows' => $rows, 'warnings' => $warnings];
    }

    /**
     * @param  list<string>  $lines
     * @return array{rows: list<array<string, string>>, warnings: list<string>}
     */
    public function assembleFromLines(array $lines): array
    {
        $warnings = [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));
        if ($lines === []) {
            return ['rows' => [], 'warnings' => []];
        }

        $headerIdx = null;
        $headerRoles = null;
        $mappingTrusted = false;

        foreach ($lines as $idx => $line) {
            $cells = $this->splitLineToCells($line);
            $analysis = $this->analyzeHeaderCells($cells);
            if ($analysis['is_header'] && $analysis['mapping_trusted']) {
                $headerIdx = $idx;
                $headerRoles = $analysis['roles'];
                $mappingTrusted = true;
                foreach ($analysis['warnings'] as $w) {
                    $warnings[] = $w;
                }

                break;
            }
        }

        if ($headerIdx !== null && $mappingTrusted && $headerRoles !== null) {
            $rows = [];
            foreach ($lines as $idx => $line) {
                if ($idx === $headerIdx) {
                    continue;
                }
                $cells = $this->splitLineToCells($line);
                if ($this->rowIsEmpty($cells)) {
                    continue;
                }
                if (! $this->looksLikeDataLine($line, $cells)) {
                    continue;
                }
                if ($this->looksLikeFooterRow($cells)) {
                    $warnings[] = __('Skipped a line that looks like a totals/footer line.');

                    continue;
                }
                $rows[] = $this->mapRowWithRoles($cells, $headerRoles, $warnings);
            }

            if ($rows === []) {
                $warnings[] = __('Header matched but no data rows were accepted; falling back to unparsed lines.');
            } else {
                return ['rows' => $rows, 'warnings' => $warnings];
            }
        }

        $warnings[] = __('Line-based fallback: column semantics unknown; joined cells stored in raw_description only.');

        $rows = [];
        foreach ($lines as $line) {
            $cells = $this->splitLineToCells($line);
            if ($this->rowIsEmpty($cells)) {
                continue;
            }
            if (! $this->looksLikeDataLine($line, $cells)) {
                continue;
            }
            if ($this->looksLikeFooterRow($cells)) {
                continue;
            }
            $rows[] = $this->unparsedRowFromCells($cells);
        }

        return ['rows' => $rows, 'warnings' => $warnings];
    }

    /**
     * @return array<string, string>
     */
    private function blankRow(): array
    {
        return array_fill_keys(self::OUTPUT_KEYS, '');
    }

    /**
     * @param  list<string>  $row
     * @param  list<string|null>  $roles  internal roles: description|qty|unit_price|tax|line_total|null (stt absorbed as null column skipped)
     */
    private function mapRowWithRoles(array $row, array $roles, array &$warnings): array
    {
        $out = $this->blankRow();
        $unmapped = [];

        foreach ($row as $i => $cell) {
            $cell = trim((string) $cell);
            $role = $roles[$i] ?? null;
            if ($role === 'stt') {
                continue;
            }
            if ($role === null) {
                if ($cell !== '') {
                    $unmapped[] = $cell;
                }

                continue;
            }
            $key = $this->roleToOutputKey($role);
            if ($key === null) {
                $unmapped[] = $cell;

                continue;
            }
            if ($out[$key] !== '') {
                $warnings[] = __('Duplicate mapped column for :key; extra value appended to raw_description.', ['key' => $key]);
                $unmapped[] = $cell;

                continue;
            }
            $out[$key] = $cell;
        }

        if ($unmapped !== []) {
            $suffix = implode(' | ', $unmapped);
            $out['raw_description'] = trim($out['raw_description'] !== '' ? $out['raw_description'].' | '.$suffix : $suffix);
            if ($suffix !== '') {
                $warnings[] = __('Some cells had no mapped column; appended to raw_description.');
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    private function unparsedRowFromCells(array $cells): array
    {
        $out = $this->blankRow();
        $out['raw_description'] = implode(' | ', array_map(trim(...), $cells));

        return $out;
    }

    /**
     * @param  list<list<string>>  $matrix
     * @return list<list<string>>
     */
    private function normalizeMatrix(array $matrix): array
    {
        $out = [];
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                continue;
            }
            $trimmed = $this->trimRow($row);
            if (! $this->rowIsEmpty($trimmed)) {
                $out[] = $trimmed;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    private function trimRow(array $row): array
    {
        return array_map(static fn (mixed $c): string => trim((string) $c), $row);
    }

    /**
     * @param  list<string>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $cells
     * @return array{is_header: bool, roles: list<string|null>, mapping_trusted: bool, warnings: list<string>}
     */
    private function analyzeHeaderRow(array $cells): array
    {
        return $this->analyzeHeaderCells($cells);
    }

    /**
     * @param  list<string>  $cells
     * @return array{is_header: bool, roles: list<string|null>, mapping_trusted: bool, warnings: list<string>}
     */
    private function analyzeHeaderCells(array $cells): array
    {
        $warnings = [];
        if (count($cells) < 2) {
            return ['is_header' => false, 'roles' => [], 'mapping_trusted' => false, 'warnings' => []];
        }

        $roles = [];
        $hits = 0;
        $usedOutputKeys = [];

        foreach ($cells as $cell) {
            $role = $this->matchColumnRole($cell);
            $roles[] = $role;
            if ($role !== null && $role !== 'stt') {
                $hits++;
                $key = $this->roleToOutputKey($role);
                if ($key !== null) {
                    $usedOutputKeys[$key] = ($usedOutputKeys[$key] ?? 0) + 1;
                }
            }
        }

        $digitHeavy = 0;
        foreach ($cells as $cell) {
            if ($this->cellLooksNumericHeavy($cell)) {
                $digitHeavy++;
            }
        }

        $isHeader = $hits >= 1 && $digitHeavy < count($cells) / 2;

        foreach ($usedOutputKeys as $key => $count) {
            if ($count > 1) {
                $warnings[] = __('Header maps multiple columns to :key; mapping not trusted.', ['key' => $key]);
            }
        }

        $hasDescription = false;
        foreach ($roles as $i => $role) {
            if ($role === 'description') {
                $hasDescription = true;
            }
        }

        $numericRoles = 0;
        foreach ($roles as $role) {
            if (in_array($role, ['qty', 'unit_price', 'tax', 'line_total'], true)) {
                $numericRoles++;
            }
        }

        $hasDuplicateMapped = count(array_filter($usedOutputKeys, fn (int $c): bool => $c > 1)) > 0;

        $mappingTrusted = $isHeader
            && ! $hasDuplicateMapped
            && $hasDescription
            && $numericRoles >= 1
            && count($cells) >= 3;

        if ($isHeader && ! $mappingTrusted) {
            $warnings[] = __('Header row detected but mapping is ambiguous (need description + at least one numeric column and no duplicate targets).');
        }

        return [
            'is_header' => $isHeader,
            'roles' => $roles,
            'mapping_trusted' => $mappingTrusted,
            'warnings' => $warnings,
        ];
    }

    private function matchColumnRole(string $header): ?string
    {
        $h = $this->foldHeader($header);
        if ($h === '') {
            return null;
        }

        $rules = [
            'stt' => '/^(stt|tt|#\s*|no\.?\s*\d*)$/u',
            'description' => '/\b(ten\s*hang|ten\s*san\s*pham|mo\s*ta|san\s*pham|hang\s*hoa|mat\s*hang|dien\s*giai|chi\s*tiet|model|item|description|product|goods)\b/u',
            'qty' => '/\b(so\s*luong|sl\b|qty|quantity|kl\b|khoi\s*luong)\b/u',
            'unit_price' => '/\b(don\s*gia|d\s*g|dg\b|unit\s*price|price\s*unit|gia\b)\b/u',
            'tax' => '/\b(thue|vat|tax|gtgt|%)\b/u',
            'line_total' => '/\b(thanh\s*tien|tien\s*hang|amount|line\s*total|total|sum|tt\b)\b/u',
        ];

        foreach ($rules as $role => $pattern) {
            if (preg_match($pattern, $h)) {
                return $role;
            }
        }

        return null;
    }

    private function roleToOutputKey(string $role): ?string
    {
        return match ($role) {
            'description' => 'raw_description',
            'qty' => 'raw_qty',
            'unit_price' => 'raw_unit_price',
            'tax' => 'raw_tax_amount',
            'line_total' => 'raw_line_total',
            default => null,
        };
    }

    private function foldHeader(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $from = 'àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ';
        $to = 'aaaaaaaaaaaaaaaaaeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd';
        $s = strtr($s, $from, $to);

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private function cellLooksNumericHeavy(string $cell): bool
    {
        $t = trim($cell);
        if ($t === '') {
            return false;
        }
        $digits = preg_match_all('/\d/u', $t) ?: 0;

        return $digits >= 3 && $digits / max(1, mb_strlen($t)) > 0.35;
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeFooterRow(array $cells): bool
    {
        $joined = $this->foldHeader(implode(' ', $cells));

        return (bool) preg_match('/\b(tong\s*cong|cong\s*tong|grand\s*total|total\s*amount|giam\s*gia|thanh\s*toan|chi\s*phi)\b/u', $joined);
    }

    private function looksLikeDataLine(string $line, array $cells): bool
    {
        if (count($cells) < 2) {
            return false;
        }
        $numericTokens = preg_match_all('/\d[\d\.,]*/u', $line) ?: 0;

        return $numericTokens >= 2 && mb_strlen($line) >= 8;
    }

    /**
     * @return list<string>
     */
    private function splitLineToCells(string $line): array
    {
        if (str_contains($line, "\t")) {
            $parts = array_map(trim(...), explode("\t", $line));

            return array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
        }

        $parts = preg_split('/\s{2,}|\s*\|\s*/u', $line) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), fn (string $p): bool => $p !== ''));
    }
}
