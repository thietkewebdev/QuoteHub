<?php

namespace App\Services\Quotation;

/**
 * Fills missing line_total (pre-tax subtotal) and total_amount from quantity × unit_price and sum of line subtotals.
 */
final class ManualQuotationPayloadEnricher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(array $payload): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = array_values(is_array($payload['items'] ?? null) ? $payload['items'] : []);

        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $q = $row['quantity'] ?? null;
            $p = $row['unit_price'] ?? null;
            $lt = $row['line_total'] ?? null;
            if ($this->isBlank($lt) && is_numeric($q) && is_numeric($p)) {
                $items[$i]['line_total'] = round((float) $q * (float) $p, 4);
            }
        }

        $payload['items'] = $items;

        $sum = 0.0;
        foreach ($items as $row) {
            if (is_array($row) && is_numeric($row['line_total'] ?? null)) {
                $sum += (float) $row['line_total'];
            }
        }

        if ($this->isBlank($payload['total_amount'] ?? null) && $sum > 0) {
            $payload['total_amount'] = $sum;
        }

        return $payload;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
