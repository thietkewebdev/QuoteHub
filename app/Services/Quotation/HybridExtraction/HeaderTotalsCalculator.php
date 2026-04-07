<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * Aggregates line subtotals / after-tax totals and compares to header totals when present.
 */
final class HeaderTotalsCalculator
{
    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function merge(array $header, array $items): array
    {
        $rel = (float) config('quotation_ai.validation.sum_lines_relative_tolerance', 0.04);
        $abs = (float) config('quotation_ai.validation.sum_lines_absolute_tolerance', 5000.0);

        $sumPre = 0.0;
        $sumPost = 0.0;
        $hasPre = false;
        $hasPost = false;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['line_total']) && is_numeric($item['line_total'])) {
                $sumPre += (float) $item['line_total'];
                $hasPre = true;
            }
            if (isset($item['line_total_after_tax']) && is_numeric($item['line_total_after_tax'])) {
                $sumPost += (float) $item['line_total_after_tax'];
                $hasPost = true;
            }
        }

        if ($hasPre && ($header['subtotal_before_tax'] ?? null) === null) {
            $header['subtotal_before_tax'] = round($sumPre, 4);
        }

        $docWarnings = [];

        $headerTotal = $header['total_amount'] ?? null;
        if ($hasPost && is_numeric($headerTotal)) {
            if (! $this->isClose($sumPost, (float) $headerTotal, $rel, $abs)) {
                $docWarnings[] = 'hybrid_header_totals: sum(line_total_after_tax) không khớp total_amount trong ngưỡng.';
            }
        } elseif ($hasPre && is_numeric($headerTotal) && ! $hasPost) {
            if (! $this->isClose($sumPre, (float) $headerTotal, $rel, $abs)) {
                $docWarnings[] = 'hybrid_header_totals: sum(line_total) không khớp total_amount trong ngưỡng.';
            }
        }

        return [$header, $docWarnings];
    }

    private function isClose(float $a, float $b, float $rel, float $abs): bool
    {
        $diff = abs($a - $b);
        $scale = max(abs($a), abs($b), 1.0);

        return ($diff / $scale) <= $rel || $diff <= $abs;
    }
}
