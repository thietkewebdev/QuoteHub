<?php

namespace App\Services\AI\Validation;

/**
 * Deterministic checks on normalized extraction JSON: numeric consistency, merged-number heuristics, confidence nudges.
 */
final class QuotationExtractionValidator
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function apply(array $normalized): array
    {
        if (! (bool) config('quotation_ai.validation.enabled', true)) {
            return $normalized;
        }

        $relLine = (float) config('quotation_ai.validation.line_total_relative_tolerance', 0.03);
        $absLine = (float) config('quotation_ai.validation.line_total_absolute_tolerance', 100.0);
        $relSum = (float) config('quotation_ai.validation.sum_lines_relative_tolerance', 0.04);
        $absSum = (float) config('quotation_ai.validation.sum_lines_absolute_tolerance', 5000.0);
        $mergedQtyMin = (float) config('quotation_ai.validation.suspicious_quantity_min', 1_000_000.0);

        $linePenalty = (float) config('quotation_ai.validation.confidence_penalty_line_mismatch', 0.92);
        $sumPenalty = (float) config('quotation_ai.validation.confidence_penalty_sum_mismatch', 0.88);
        $mergedPenalty = (float) config('quotation_ai.validation.confidence_penalty_suspicious_merge', 0.88);
        $fieldPenalty = (float) config('quotation_ai.validation.confidence_penalty_field', 0.9);

        /** @var list<string> $docWarnings */
        $docWarnings = is_array($normalized['document_warnings'] ?? null)
            ? $normalized['document_warnings']
            : [];
        /** @var list<array<string, mixed>> $items */
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        /** @var array<string, mixed> $header */
        $header = is_array($normalized['quotation_header'] ?? null) ? $normalized['quotation_header'] : [];
        $overall = (float) ($normalized['overall_confidence'] ?? 0.0);

        $issueCount = 0;

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $q = $item['quantity'] ?? null;
            $p = $item['unit_price'] ?? null;
            $t = $item['line_total'] ?? null;

            if (is_float($q) && $q >= $mergedQtyMin && $p === null && $t !== null) {
                $warnings = is_array($item['warnings'] ?? null) ? $item['warnings'] : [];
                $warnings[] = 'Cảnh báo xác thực: quantity rất lớn và thiếu đơn giá — có thể số bị dính (merged) do mất cột OCR.';
                $items[$i]['warnings'] = array_values(array_unique(array_map(fn ($w) => (string) $w, $warnings)));
                $items[$i]['confidence_score'] = $this->clamp01((float) ($item['confidence_score'] ?? 0.0) * $mergedPenalty);
                $items[$i]['field_confidence'] = $this->scaleFields(
                    is_array($item['field_confidence'] ?? null) ? $item['field_confidence'] : [],
                    ['quantity', 'line_total'],
                    $fieldPenalty,
                );
                $overall *= $mergedPenalty;
                $issueCount++;
            }

            $item = $items[$i];
            $q = $item['quantity'] ?? null;
            $p = $item['unit_price'] ?? null;
            $t = $item['line_total'] ?? null;
            $pAfter = $item['unit_price_after_tax'] ?? null;
            $tAfter = $item['line_total_after_tax'] ?? null;

            if ($q !== null && $p !== null && $t !== null) {
                $expectedPre = (float) $q * (float) $p;
                if (! $this->isClose($expectedPre, (float) $t, $relLine, $absLine)) {
                    $warnings = is_array($item['warnings'] ?? null) ? $item['warnings'] : [];
                    $warnings[] = 'Cảnh báo xác thực: quantity × unit_price không khớp line_total (trước thuế) trong ngưỡng cho phép.';
                    $items[$i]['warnings'] = array_values(array_unique(array_map(fn ($w) => (string) $w, $warnings)));
                    $items[$i]['confidence_score'] = $this->clamp01((float) ($item['confidence_score'] ?? 0.0) * $linePenalty);
                    $items[$i]['field_confidence'] = $this->scaleFields(
                        is_array($item['field_confidence'] ?? null) ? $item['field_confidence'] : [],
                        ['quantity', 'unit_price', 'line_total'],
                        $fieldPenalty,
                    );
                    $overall *= $linePenalty;
                    $issueCount++;
                }
            }

            if ($q !== null && is_numeric($pAfter) && (float) $pAfter > 0 && $tAfter !== null && is_numeric($tAfter)) {
                $expectedPost = (float) $q * (float) $pAfter;
                if (! $this->isClose($expectedPost, (float) $tAfter, $relLine, $absLine)) {
                    $warnings = is_array($items[$i]['warnings'] ?? null) ? $items[$i]['warnings'] : [];
                    $warnings[] = 'Cảnh báo xác thực: quantity × unit_price_after_tax không khớp line_total_after_tax trong ngưỡng cho phép.';
                    $items[$i]['warnings'] = array_values(array_unique(array_map(fn ($w) => (string) $w, $warnings)));
                    $items[$i]['confidence_score'] = $this->clamp01((float) ($items[$i]['confidence_score'] ?? 0.0) * $linePenalty);
                    $items[$i]['field_confidence'] = $this->scaleFields(
                        is_array($items[$i]['field_confidence'] ?? null) ? $items[$i]['field_confidence'] : [],
                        ['quantity', 'unit_price_after_tax', 'line_total_after_tax'],
                        $fieldPenalty,
                    );
                    $overall *= $linePenalty;
                    $issueCount++;
                }
            }
        }

        $totalAmount = $header['total_amount'] ?? null;
        $lineTotals = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['line_total_after_tax']) && is_numeric($item['line_total_after_tax'])) {
                $lineTotals[] = (float) $item['line_total_after_tax'];
            } elseif (isset($item['line_total']) && is_numeric($item['line_total'])) {
                $lineTotals[] = (float) $item['line_total'];
            }
        }
        if ($totalAmount !== null && is_numeric($totalAmount) && $lineTotals !== []) {
            $sum = array_sum($lineTotals);
            $ta = (float) $totalAmount;
            if (! $this->isClose($sum, $ta, $relSum, $absSum)) {
                $docWarnings[] = 'Cảnh báo xác thực: tổng Thành tiền các dòng không khớp total_amount trong ngưỡng.';
                $overall *= $sumPenalty;
                $issueCount++;
                $header['field_confidence'] = $this->scaleFields(
                    is_array($header['field_confidence'] ?? null) ? $header['field_confidence'] : [],
                    ['total_amount'],
                    $fieldPenalty,
                );
            }
        }

        $normalized['items'] = $items;
        $normalized['quotation_header'] = $header;
        $normalized['document_warnings'] = array_values(array_unique(array_map(fn ($w) => (string) $w, $docWarnings)));
        $normalized['overall_confidence'] = $this->clamp01($overall);

        $meta = is_array($normalized['extraction_meta'] ?? null) ? $normalized['extraction_meta'] : [];
        $meta['validation_applied'] = true;
        $meta['validation_issue_count'] = $issueCount;
        $normalized['extraction_meta'] = $meta;

        return $normalized;
    }

    private function isClose(float $a, float $b, float $relativeTolerance, float $absoluteTolerance): bool
    {
        $diff = abs($a - $b);
        $scale = max(abs($a), abs($b), 1.0);
        if (($diff / $scale) <= $relativeTolerance) {
            return true;
        }

        return $diff <= $absoluteTolerance;
    }

    /**
     * @param  array<string, float>  $fields
     * @param  list<string>  $keys
     * @return array<string, float>
     */
    private function scaleFields(array $fields, array $keys, float $factor): array
    {
        foreach ($keys as $k) {
            if (isset($fields[$k])) {
                $fields[$k] = $this->clamp01($fields[$k] * $factor);
            }
        }

        return $fields;
    }

    private function clamp01(float $v): float
    {
        if ($v < 0.0) {
            return 0.0;
        }
        if ($v > 1.0) {
            return 1.0;
        }

        return $v;
    }
}
