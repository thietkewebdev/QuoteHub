<?php

namespace App\Services\AI\Correction;

/**
 * Lightweight numeric fixes before validation. Does not modify raw_name / raw_model / brand text.
 * Logs each change under extraction_meta.auto_corrections.
 */
final class QuotationExtractionAutoCorrector
{
    public function __construct(
        private readonly PerUnitTaxAmountFromWarningsParser $taxWarningParser,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function apply(array $normalized): array
    {
        if (! (bool) config('quotation_ai.auto_correct.enabled', true)) {
            return $normalized;
        }

        $rel = (float) config('quotation_ai.auto_correct.split_match_relative_tolerance', 0.03);
        $abs = (float) config('quotation_ai.auto_correct.split_match_absolute_tolerance', 500.0);
        $vatPercentMax = (float) config('quotation_ai.auto_correct.vat_percent_max_plausible', 100.0);
        $ratioTol = (float) config('quotation_ai.auto_correct.vat_per_unit_ratio_tolerance', 0.004);
        $recovery = (float) config('quotation_ai.auto_correct.confidence_recovery_per_fix', 0.025);
        $mergedQtyMin = (float) config('quotation_ai.validation.suspicious_quantity_min', 1_000_000.0);
        $lineRel = (float) config('quotation_ai.validation.line_total_relative_tolerance', 0.03);
        $lineAbs = (float) config('quotation_ai.validation.line_total_absolute_tolerance', 100.0);

        /** @var list<array<string, mixed>> $corrections */
        $corrections = [];
        /** @var list<array<string, mixed>> $items */
        $items = is_array($normalized['items'] ?? null) ? $normalized['items'] : [];
        $overall = (float) ($normalized['overall_confidence'] ?? 0.0);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $lineNo = (int) ($item['line_no'] ?? ($i + 1));

            $q = $items[$i]['quantity'] ?? null;
            $p = $items[$i]['unit_price'] ?? null;
            $t = $items[$i]['line_total'] ?? null;

            if (is_numeric($q) && $p === null && is_numeric($t) && (float) $q >= $mergedQtyMin) {
                $split = $this->trySplitMergedQuantityTimesPrice((float) $q, (float) $t, $rel, $abs);
                if ($split !== null) {
                    [$newQ, $newP] = $split;
                    $corrections[] = [
                        'type' => 'merged_quantity_split',
                        'line_no' => $lineNo,
                        'detail' => 'Split concatenated quantity×unit_price digit pattern to match line_total',
                        'before' => ['quantity' => (float) $q, 'unit_price' => null],
                        'after' => ['quantity' => $newQ, 'unit_price' => $newP],
                    ];
                    $items[$i]['quantity'] = $newQ;
                    $items[$i]['unit_price'] = $newP;
                    $this->appendWarning($items[$i], 'Tự điều chỉnh: tách số quantity dính (merged) thành SL và đơn giá.');
                }
            }

            $this->tryApplyVatPerUnitInference($items[$i], $lineNo, $vatPercentMax, $ratioTol, $lineRel, $lineAbs, $corrections);

            if (is_numeric($items[$i]['vat_percent'] ?? null) && (float) $items[$i]['vat_percent'] > $vatPercentMax) {
                $before = (float) $items[$i]['vat_percent'];
                $items[$i]['vat_percent'] = null;
                $corrections[] = [
                    'type' => 'vat_percent_cleared_likely_currency',
                    'line_no' => $lineNo,
                    'detail' => 'vat_percent > '.$vatPercentMax.' treated as currency column mis-mapped; cleared vat_percent (text fields unchanged).',
                    'before' => ['vat_percent' => $before],
                    'after' => ['vat_percent' => null],
                ];
                $this->appendWarning($items[$i], 'Tự điều chỉnh: cột VAT có thể là số tiền (VNĐ) — đã bỏ giá trị % sai.');
            }

            $q = $items[$i]['quantity'] ?? null;
            $p = $items[$i]['unit_price'] ?? null;
            $t = $items[$i]['line_total'] ?? null;

            if (is_numeric($q) && $p === null && is_numeric($t) && (float) $q > 0) {
                $inferred = (float) $t / (float) $q;
                if (is_finite($inferred) && $inferred > 0) {
                    $corrections[] = [
                        'type' => 'unit_price_inferred_from_line_total',
                        'line_no' => $lineNo,
                        'detail' => 'unit_price = line_total / quantity',
                        'before' => ['unit_price' => null],
                        'after' => ['unit_price' => $inferred],
                    ];
                    $items[$i]['unit_price'] = $inferred;
                    $this->appendWarning($items[$i], 'Tự điều chỉnh: suy ra đơn giá từ thành tiền ÷ số lượng.');
                }
            }
        }

        $normalized['items'] = $items;
        $meta = is_array($normalized['extraction_meta'] ?? null) ? $normalized['extraction_meta'] : [];
        $meta['auto_corrections'] = $corrections;
        $meta['auto_correct_applied'] = $corrections !== [];
        $normalized['extraction_meta'] = $meta;

        if ($corrections !== []) {
            $normalized['overall_confidence'] = min(1.0, $overall + $recovery * count($corrections));
        }

        return $normalized;
    }

    /**
     * When tax_per_unit / unit_price ≈ 8% or 10% VAT, normalize line economics.
     * When quantity × unit_price_after_tax matches extracted line_total (within validation tolerance), treat extracted
     * line_total as sau thuế: set line_total = trước thuế, line_total_after_tax = extracted, and add warning after_tax_total_detected.
     * Tax may come from tax_per_unit, mis-mapped vat_percent, or a VND amount parsed from warnings.
     *
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $corrections
     */
    private function tryApplyVatPerUnitInference(
        array &$item,
        int $lineNo,
        float $vatPercentMax,
        float $ratioTolerance,
        float $lineRelTolerance,
        float $lineAbsTolerance,
        array &$corrections,
    ): void {
        $unitPrice = $item['unit_price'] ?? null;
        if (! is_numeric($unitPrice) || (float) $unitPrice <= 0) {
            return;
        }
        $u = (float) $unitPrice;

        $explicitTax = $item['tax_per_unit'] ?? null;
        $vatRaw = $item['vat_percent'] ?? null;
        $warnings = is_array($item['warnings'] ?? null) ? $item['warnings'] : [];
        $taxFromWarnings = $this->taxWarningParser->parse($warnings);

        $tax = null;
        $source = '';

        if (is_numeric($explicitTax) && (float) $explicitTax > 0) {
            $tax = (float) $explicitTax;
            $source = 'tax_per_unit';
        } elseif (is_numeric($vatRaw) && (float) $vatRaw > $vatPercentMax) {
            $tax = (float) $vatRaw;
            $source = 'vat_percent_as_tax_amount';
        } elseif ($taxFromWarnings !== null) {
            $tax = $taxFromWarnings;
            $source = 'warnings_vnd_per_unit';
        }

        if ($tax === null || $tax <= 0) {
            return;
        }

        $ratio = $tax / $u;
        if (! $this->ratioMatchesStandardVnVat($ratio, $ratioTolerance)) {
            return;
        }

        $qty = $item['quantity'] ?? null;
        $hasQty = is_numeric($qty) && (float) $qty > 0;
        $q = $hasQty ? (float) $qty : 0.0;
        $extractedLineTotal = $item['line_total'] ?? null;
        $extractedLineTotal = is_numeric($extractedLineTotal) ? (float) $extractedLineTotal : null;

        $vatPercentLabel = $this->chooseEightOrTenPercent($ratio);
        $unitAfter = $u + $tax;

        if ($source === 'warnings_vnd_per_unit' && (! $hasQty || $extractedLineTotal === null)) {
            $before = [
                'vat_percent' => $item['vat_percent'] ?? null,
                'tax_per_unit' => $item['tax_per_unit'] ?? null,
                'unit_price_after_tax' => $item['unit_price_after_tax'] ?? null,
            ];
            $item['tax_per_unit'] = $tax;
            $item['vat_percent'] = $vatPercentLabel;
            $item['unit_price_after_tax'] = $unitAfter;
            $corrections[] = [
                'type' => 'vat_per_unit_inferred',
                'line_no' => $lineNo,
                'detail' => 'Per-unit tax from warnings and 8%/10% VAT; quantity or line_total missing — VAT scalars only',
                'source' => $source,
                'before' => $before,
                'after' => [
                    'vat_percent' => $vatPercentLabel,
                    'tax_per_unit' => $tax,
                    'unit_price_after_tax' => $unitAfter,
                ],
            ];
            $this->appendWarning($item, 'Tự điều chỉnh: thuế theo cảnh báo (VNĐ/đơn vị) — điền % VAT; thiếu SL hoặc thành tiền để đối chiếu dòng.');

            return;
        }

        $before = [
            'vat_percent' => $item['vat_percent'] ?? null,
            'tax_per_unit' => $item['tax_per_unit'] ?? null,
            'unit_price_after_tax' => $item['unit_price_after_tax'] ?? null,
            'line_total_before_tax' => $item['line_total_before_tax'] ?? null,
            'line_total_after_tax' => $item['line_total_after_tax'] ?? null,
            'line_total' => $item['line_total'] ?? null,
        ];

        $item['tax_per_unit'] = $tax;
        $item['vat_percent'] = $vatPercentLabel;
        $item['unit_price_after_tax'] = $unitAfter;

        $afterTaxTotalVerified = false;
        if ($hasQty) {
            $linePre = $q * $u;
            $linePostComputed = $q * $unitAfter;
            $item['line_total_before_tax'] = $linePre;

            if ($extractedLineTotal !== null) {
                $afterTaxTotalVerified = $this->isClose($linePostComputed, $extractedLineTotal, $lineRelTolerance, $lineAbsTolerance);
                if ($afterTaxTotalVerified) {
                    $item['line_total'] = $linePre;
                    $item['line_total_after_tax'] = $extractedLineTotal;
                    $this->appendWarning($item, 'after_tax_total_detected');
                    $this->appendWarning($item, 'Tự điều chỉnh: quantity × đơn giá sau thuế khớp thành tiền trích — line_total = trước thuế, line_total_after_tax = giá trị trích.');
                } else {
                    $item['line_total_after_tax'] = $linePostComputed;
                    $this->appendWarning($item, 'Tự điều chỉnh: thuế 8%/10% — line_total_after_tax = SL × đơn giá sau thuế; giữ line_total trích (chưa khớp sau thuế trong ngưỡng).');
                }
            } else {
                $item['line_total'] = $linePre;
                $item['line_total_after_tax'] = $linePostComputed;
                $this->appendWarning($item, 'Tự điều chỉnh: thuế GTGT theo đơn vị (8%/10%) — line_total = trước thuế, line_total_after_tax = sau thuế.');
            }
        } else {
            $item['line_total_before_tax'] = null;
        }

        $corrections[] = [
            'type' => 'vat_per_unit_inferred',
            'line_no' => $lineNo,
            'detail' => $afterTaxTotalVerified
                ? 'Verified quantity×unit_price_after_tax ≈ extracted line_total; stored pre-tax line_total and after_tax_total_detected'
                : 'Per-unit tax matches 8% or 10% VAT; line fields per reconciliation rules',
            'source' => $source,
            'after_tax_total_verified' => $afterTaxTotalVerified,
            'before' => $before,
            'after' => [
                'vat_percent' => $vatPercentLabel,
                'tax_per_unit' => $tax,
                'unit_price_after_tax' => $unitAfter,
                'line_total_before_tax' => $hasQty ? $q * $u : null,
                'line_total_after_tax' => $hasQty ? ($item['line_total_after_tax'] ?? null) : null,
                'line_total' => $item['line_total'] ?? null,
            ],
        ];
    }

    private function chooseEightOrTenPercent(float $ratio): float
    {
        return abs($ratio - 0.08) <= abs($ratio - 0.10) ? 8.0 : 10.0;
    }

    private function ratioMatchesStandardVnVat(float $ratio, float $tolerance): bool
    {
        foreach ([0.08, 0.10] as $target) {
            if (abs($ratio - $target) <= $tolerance) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function appendWarning(array &$item, string $message): void
    {
        $w = is_array($item['warnings'] ?? null) ? $item['warnings'] : [];
        $w[] = $message;
        $item['warnings'] = array_values(array_unique(array_map(fn ($x) => (string) $x, $w)));
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function trySplitMergedQuantityTimesPrice(float $mergedQuantity, float $lineTotal, float $relTol, float $absTol): ?array
    {
        if ($mergedQuantity < 1_000) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) (int) round($mergedQuantity));
        if ($digits === '' || strlen($digits) < 4) {
            return null;
        }

        for ($i = 1; $i < strlen($digits); $i++) {
            $a = (float) substr($digits, 0, $i);
            $b = (float) substr($digits, $i);
            if ($a <= 0.0 || $b <= 0.0) {
                continue;
            }
            $prod = $a * $b;
            if ($this->isClose($prod, $lineTotal, $relTol, $absTol)) {
                return [$a, $b];
            }
        }

        return null;
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
}
