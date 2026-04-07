<?php

namespace App\Services\Quotation;

/**
 * Parses tab- or newline-oriented spreadsheet paste into manual quotation line item rows.
 *
 * Column order (0-based): raw_name, raw_model, brand, unit, quantity, unit_price (excl. VAT), vat_percent, line_total (excl. VAT), specs_text.
 * Missing trailing columns are allowed; extra columns are ignored.
 */
final class LineItemsPasteParser
{
    public const COLUMN_KEYS = [
        'raw_name',
        'raw_model',
        'brand',
        'unit',
        'quantity',
        'unit_price',
        'vat_percent',
        'line_total',
        'specs_text',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $raw, bool $skipFirstLine = false): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn (string $l): bool => $l !== ''));

        if ($skipFirstLine && $lines !== []) {
            array_shift($lines);
        }

        $out = [];
        foreach ($lines as $line) {
            $cells = $this->splitLineIntoCells($line);
            if ($cells === []) {
                continue;
            }
            $row = $this->mapCellsToItem($cells);
            if (trim((string) ($row['raw_name'] ?? '')) === '') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitLineIntoCells(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        if (str_contains($line, ';') && ! str_contains($line, ',')) {
            return array_map('trim', explode(';', $line));
        }

        if (str_contains($line, ',')) {
            return array_map('trim', str_getcsv($line, ',', '"'));
        }

        return [trim($line)];
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, mixed>
     */
    private function mapCellsToItem(array $cells): array
    {
        $row = [];
        foreach (self::COLUMN_KEYS as $i => $key) {
            $cell = $cells[$i] ?? '';
            $row[$key] = match ($key) {
                'quantity', 'unit_price', 'vat_percent', 'line_total' => $this->parseNumber($cell),
                default => $cell,
            };
        }
        $row['mapped_product_id'] = null;

        return $row;
    }

    private function parseNumber(string $value): ?float
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($s === '') {
            return null;
        }

        // Vietnamese-style grouping: 1.234.567 or 1.234.567,89
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s) === 1) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $s) === 1) {
            $s = str_replace(',', '', $s);
        } elseif (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
