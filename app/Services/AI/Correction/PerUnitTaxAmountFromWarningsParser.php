<?php

namespace App\Services\AI\Correction;

/**
 * Extracts per-line tax amounts in VND mentioned in model warnings (e.g. "Thuế dòng (VNĐ): 332000").
 */
final class PerUnitTaxAmountFromWarningsParser
{
    /**
     * @param  list<mixed>  $warnings
     */
    public function parse(array $warnings): ?float
    {
        foreach ($warnings as $w) {
            $s = trim((string) $w);
            if ($s === '') {
                continue;
            }
            foreach ($this->patterns() as $pattern) {
                if (preg_match($pattern, $s, $m) === 1) {
                    $n = $this->parseVnNumericToken($m[1] ?? '');
                    if ($n !== null && $n > 0) {
                        return $n;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function patterns(): array
    {
        return [
            '/Thuế\s*dòng\s*\(\s*VNĐ\s*\)\s*:\s*([\d][\d\s.,]*)/iu',
            '/thuế\s*dòng\s*\(\s*VNĐ\s*\)\s*:\s*([\d][\d\s.,]*)/iu',
            '/\(\s*VNĐ\s*\)\s*:\s*([\d][\d\s.,]*)/iu',
            '/VNĐ\s*:\s*([\d][\d\s.,]{3,})/iu',
        ];
    }

    private function parseVnNumericToken(string $raw): ?float
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        $t = preg_replace('/\s+/u', '', $t) ?? $t;
        // Vietnamese grouping: 1.234.567
        if (str_contains($t, '.') && ! str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
        } elseif (str_contains($t, ',') && str_contains($t, '.')) {
            // 1,234,567 US style → remove commas
            $t = str_replace(',', '', $t);
        } else {
            $t = str_replace([',', ' '], '', $t);
        }
        if ($t === '' || ! is_numeric($t)) {
            return null;
        }

        return (float) $t;
    }
}
