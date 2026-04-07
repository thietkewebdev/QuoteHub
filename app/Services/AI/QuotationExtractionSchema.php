<?php

namespace App\Services\AI;

use App\Models\AiExtraction;
use InvalidArgumentException;

/**
 * Canonical quotation extraction JSON shape for {@see AiExtraction::$extraction_json}.
 */
final class QuotationExtractionSchema
{
    /**
     * @return list<string>
     */
    public static function headerFieldConfidenceKeys(): array
    {
        return [
            'supplier_name',
            'supplier_quote_number',
            'quote_date',
            'valid_until',
            'currency',
            'subtotal_before_tax',
            'tax_amount',
            'total_amount',
            'contact_person',
            'notes',
        ];
    }

    /**
     * @return list<string>
     */
    public static function itemFieldConfidenceKeys(): array
    {
        return [
            'raw_name',
            'raw_model',
            'brand',
            'unit',
            'quantity',
            'unit_price',
            'vat_percent',
            'tax_per_unit',
            'unit_price_after_tax',
            'line_total',
            'line_total_before_tax',
            'line_total_after_tax',
            'warranty_text',
            'origin_text',
            'specs_text',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function template(): array
    {
        return [
            'quotation_header' => [
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
            ],
            'items' => [],
            'document_warnings' => [],
            'overall_confidence' => 0.0,
            'extraction_meta' => [
                'engine_version' => '',
                'pass_count' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemTemplate(): array
    {
        return [
            'line_no' => 1,
            'raw_name' => '',
            'raw_model' => '',
            'brand' => '',
            'unit' => '',
            'quantity' => null,
            'unit_price' => null,
            'vat_percent' => null,
            'unit_price_after_tax' => null,
            'line_total' => null,
            'warranty_text' => '',
            'origin_text' => '',
            'specs_text' => '',
            'confidence_score' => 0.0,
            'field_confidence' => [],
            'warnings' => [],
        ];
    }

    /**
     * Merge provider output into the strict template and coerce scalar types.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $out = array_replace_recursive(self::template(), $raw);

        $header = is_array($out['quotation_header'] ?? null) ? $out['quotation_header'] : [];
        $out['quotation_header'] = array_replace(self::template()['quotation_header'], $header);
        $out['quotation_header']['currency'] = (string) ($out['quotation_header']['currency'] ?? 'VND');
        foreach (['subtotal_before_tax', 'tax_amount', 'total_amount'] as $key) {
            $out['quotation_header'][$key] = self::nullableNumber($out['quotation_header'][$key] ?? null);
        }
        foreach (['supplier_name', 'supplier_quote_number', 'quote_date', 'valid_until', 'contact_person', 'notes'] as $key) {
            $out['quotation_header'][$key] = (string) ($out['quotation_header'][$key] ?? '');
        }
        $out['quotation_header']['field_confidence'] = self::normalizeFieldConfidenceMap(
            $out['quotation_header']['field_confidence'] ?? [],
            self::headerFieldConfidenceKeys(),
        );

        $itemsIn = is_array($out['items'] ?? null) ? $out['items'] : [];
        $items = [];
        foreach (array_values($itemsIn) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $item = array_replace(self::itemTemplate(), $row);
            $item['line_no'] = $index + 1;
            $item['raw_name'] = (string) ($item['raw_name'] ?? '');
            $item['raw_model'] = (string) ($item['raw_model'] ?? '');
            $item['brand'] = (string) ($item['brand'] ?? '');
            $item['unit'] = (string) ($item['unit'] ?? '');
            foreach ([
                'quantity',
                'unit_price',
                'vat_percent',
                'tax_per_unit',
                'unit_price_after_tax',
                'line_total',
                'line_total_before_tax',
                'line_total_after_tax',
            ] as $key) {
                $item[$key] = self::nullableNumber($item[$key] ?? null);
            }
            $item['warranty_text'] = (string) ($item['warranty_text'] ?? '');
            $item['origin_text'] = (string) ($item['origin_text'] ?? '');
            $item['specs_text'] = (string) ($item['specs_text'] ?? '');
            $item['confidence_score'] = self::floatish($item['confidence_score'] ?? 0.0);
            $item['field_confidence'] = self::normalizeFieldConfidenceMap(
                $item['field_confidence'] ?? [],
                self::itemFieldConfidenceKeys(),
            );
            $warnings = $item['warnings'] ?? [];
            $item['warnings'] = is_array($warnings)
                ? array_values(array_map(fn ($w) => (string) $w, $warnings))
                : [];
            $items[] = $item;
        }
        $out['items'] = $items;

        $docWarnings = $out['document_warnings'] ?? [];
        $out['document_warnings'] = is_array($docWarnings)
            ? array_values(array_map(fn ($w) => (string) $w, $docWarnings))
            : [];

        $out['overall_confidence'] = self::floatish($out['overall_confidence'] ?? 0.0);

        $meta = is_array($out['extraction_meta'] ?? null) ? $out['extraction_meta'] : [];
        $out['extraction_meta'] = array_merge(self::template()['extraction_meta'], $meta);
        $out['extraction_meta']['engine_version'] = (string) ($out['extraction_meta']['engine_version'] ?? '');
        $out['extraction_meta']['pass_count'] = max(0, (int) ($out['extraction_meta']['pass_count'] ?? 0));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertValid(array $data): void
    {
        foreach (['quotation_header', 'items', 'document_warnings', 'overall_confidence', 'extraction_meta'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing key: {$key}");
            }
        }
        if (! is_array($data['quotation_header']) || ! is_array($data['items']) || ! is_array($data['document_warnings']) || ! is_array($data['extraction_meta'])) {
            throw new InvalidArgumentException('Invalid quotation extraction structure.');
        }
        $hKeys = array_keys(self::template()['quotation_header']);
        foreach ($hKeys as $k) {
            if (! array_key_exists($k, $data['quotation_header'])) {
                throw new InvalidArgumentException("Missing quotation_header.{$k}");
            }
        }
        foreach ($data['items'] as $i => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("items[{$i}] must be an array.");
            }
            foreach (array_keys(self::itemTemplate()) as $k) {
                if (! array_key_exists($k, $item)) {
                    throw new InvalidArgumentException("Missing items[{$i}].{$k}");
                }
            }
        }
        foreach (['engine_version', 'pass_count'] as $mk) {
            if (! array_key_exists($mk, $data['extraction_meta'])) {
                throw new InvalidArgumentException("Missing extraction_meta.{$mk}");
            }
        }
    }

    /**
     * @param  list<string>  $allowedKeys
     * @return array<string, float>
     */
    public static function normalizeFieldConfidenceMap(mixed $raw, array $allowedKeys): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $allowed = array_flip($allowedKeys);
        $out = [];
        foreach ($raw as $k => $v) {
            $key = (string) $k;
            if (! isset($allowed[$key])) {
                continue;
            }
            if (! is_numeric($v)) {
                continue;
            }
            $f = (float) $v;
            if ($f < 0.0) {
                $f = 0.0;
            }
            if ($f > 1.0) {
                $f = 1.0;
            }
            $out[$key] = $f;
        }

        return $out;
    }

    protected static function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    protected static function floatish(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }
}
