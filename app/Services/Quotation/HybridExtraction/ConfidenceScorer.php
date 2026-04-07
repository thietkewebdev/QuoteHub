<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * Derives overall confidence from per-line scores and hybrid pipeline signals.
 */
final class ConfidenceScorer
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function overall(array $items, float $baseFromLlm = 0.72): float
    {
        if ($items === []) {
            return 0.0;
        }

        $scores = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $c = $item['confidence_score'] ?? null;
            if (is_numeric($c)) {
                $scores[] = (float) $c;
            }
        }

        if ($scores === []) {
            return $this->clamp01($baseFromLlm);
        }

        $mean = array_sum($scores) / count($scores);

        return $this->clamp01(($mean + $baseFromLlm) / 2);
    }

    private function clamp01(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
