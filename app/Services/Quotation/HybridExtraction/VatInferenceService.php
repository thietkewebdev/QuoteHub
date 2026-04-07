<?php

namespace App\Services\Quotation\HybridExtraction;

/**
 * When tax_per_unit / unit_price ≈ 8% or 10%, normalize line economics (before/after tax).
 */
final class VatInferenceService
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function apply(array &$items): void
    {
        $ratioTol = (float) config('quotation_ai.auto_correct.vat_per_unit_ratio_tolerance', 0.004);
        $vatMax = (float) config('quotation_ai.auto_correct.vat_percent_max_plausible', 100.0);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $unitPrice = $item['unit_price'] ?? null;
            if (! is_numeric($unitPrice) || (float) $unitPrice <= 0) {
                continue;
            }
            $u = (float) $unitPrice;

            $tax = $this->resolveTaxPerUnit($item, $vatMax);
            if ($tax === null || $tax <= 0) {
                continue;
            }

            $ratio = $tax / $u;
            if (! $this->ratioMatchesStandardVnVat($ratio, $ratioTol)) {
                continue;
            }

            $qty = $item['quantity'] ?? null;
            $q = (is_numeric($qty) && (float) $qty > 0) ? (float) $qty : null;

            $vatPercent = abs($ratio - 0.08) <= abs($ratio - 0.10) ? 8.0 : 10.0;
            $unitAfter = $u + $tax;

            $items[$i]['tax_per_unit'] = $tax;
            $items[$i]['vat_percent'] = $vatPercent;
            $items[$i]['unit_price_after_tax'] = $unitAfter;

            if ($q !== null) {
                $items[$i]['line_total'] = round($q * $u, 4);
                $items[$i]['line_total_after_tax'] = round($q * $unitAfter, 4);
            }

            $w = is_array($items[$i]['warnings'] ?? null) ? $items[$i]['warnings'] : [];
            $w[] = 'hybrid_vat_inference: tax_per_unit ÷ unit_price ≈ '.round($ratio * 100, 2).'% — normalized before/after tax.';
            $items[$i]['warnings'] = array_values(array_unique(array_map(fn ($x) => (string) $x, $w)));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveTaxPerUnit(array $item, float $vatPercentMax): ?float
    {
        $explicit = $item['tax_per_unit'] ?? null;
        if (is_numeric($explicit) && (float) $explicit > 0) {
            return (float) $explicit;
        }

        $vatRaw = $item['vat_percent'] ?? null;
        if (is_numeric($vatRaw) && (float) $vatRaw > $vatPercentMax) {
            return (float) $vatRaw;
        }

        return null;
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
}
