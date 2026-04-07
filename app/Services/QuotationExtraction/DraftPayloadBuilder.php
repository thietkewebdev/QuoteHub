<?php

namespace App\Services\QuotationExtraction;

use App\Services\AI\QuotationExtractionSchema;
use App\Services\Quotation\QuotationReviewPayloadFactory;

/**
 * Normalizes raw OCR quotation rows (repair → split → parse numbers → deterministic totals → VAT inference → validate)
 * and builds Filament review payload. Final line math is never delegated to an LLM.
 */
final class DraftPayloadBuilder
{
    public function __construct(
        private readonly VietnameseOcrRepairService $ocrRepair,
        private readonly ProductNameSplitter $nameSplitter,
        private readonly VatInferenceService $vatInference,
        private readonly NumericConsistencyValidator $numericValidator,
        private readonly QuotationReviewPayloadFactory $payloadFactory,
    ) {}

    /**
     * @param  list<array<string, string>>  $rawRows  rows from {@see RawQuotationExtractionService}
     * @param  array<string, mixed>  $quotationHeader  partial header merged into schema template
     * @param  list<string>  $documentWarnings
     * @return array<string, mixed> normalized extraction JSON (same shape as AI extraction)
     */
    public function buildNormalizedExtractionFromRawRows(
        array $rawRows,
        array $quotationHeader = [],
        array $documentWarnings = [],
        float $overallConfidence = 0.75,
    ): array {
        $items = [];
        foreach (array_values($rawRows) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $items[] = $this->buildItemFromRawRow($raw, $index);
        }

        $this->vatInference->apply($items);

        $header = array_replace(
            QuotationExtractionSchema::template()['quotation_header'],
            $quotationHeader,
        );

        $rawDoc = [
            'quotation_header' => $header,
            'items' => $items,
            'document_warnings' => array_values(array_map(fn ($w) => (string) $w, $documentWarnings)),
            'overall_confidence' => $overallConfidence,
            'extraction_meta' => [
                'engine_version' => 'quotation-extraction-ocr-v1',
                'pass_count' => 1,
            ],
        ];

        $normalized = QuotationExtractionSchema::normalize($rawDoc);

        return $this->numericValidator->apply($normalized);
    }

    /**
     * @param  array<string, mixed>  $normalizedExtraction  output of {@see self::buildNormalizedExtractionFromRawRows} or {@see QuotationExtractionSchema::normalize}
     * @return array<string, mixed> review UI payload
     */
    public function buildReviewPayload(array $normalizedExtraction): array
    {
        return $this->payloadFactory->fromExtractionJson($normalizedExtraction);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function buildItemFromRawRow(array $row, int $lineIndex): array
    {
        $warnings = [];

        $desc = $this->ocrRepair->repair((string) ($row['raw_description'] ?? ''));
        [$rawName, $specs] = $this->nameSplitter->split($desc, '');

        $q = $this->parseAmount((string) ($row['raw_qty'] ?? ''));
        $u = $this->parseAmount((string) ($row['raw_unit_price'] ?? ''));
        $taxParsed = $this->parseAmount((string) ($row['raw_tax_amount'] ?? ''));
        $lineParsed = $this->parseAmount((string) ($row['raw_line_total'] ?? ''));

        $taxPerUnit = $this->inferTaxPerUnitFromRawTax($q, $u, $taxParsed);
        if ($taxParsed !== null && $taxParsed > 0 && $taxPerUnit === null) {
            $warnings[] = 'ocr_row: raw_tax_amount could not be interpreted as a consistent VAT amount; VAT inference may still apply from ratios.';
        }

        $lineBefore = ($q !== null && $u !== null && $q > 0 && $u >= 0) ? round($q * $u, 4) : null;
        if ($lineBefore !== null && $lineParsed !== null && ! $this->amountsClose($lineBefore, $lineParsed)) {
            $warnings[] = 'ocr_row: raw_line_total disagrees with quantity×unit_price; line_total stays before-tax from quantity×unit_price.';
        }

        $unitAfter = null;
        $lineAfter = null;
        if ($lineBefore !== null && $taxPerUnit !== null && $q !== null && $u !== null && $q > 0) {
            $unitAfter = round($u + $taxPerUnit, 4);
            $lineAfter = round($q * $unitAfter, 4);
        }

        return [
            'line_no' => $lineIndex + 1,
            'raw_name' => $rawName,
            'raw_model' => '',
            'brand' => '',
            'unit' => '',
            'quantity' => $q,
            'unit_price' => $u,
            'vat_percent' => null,
            'tax_per_unit' => $taxPerUnit,
            'unit_price_after_tax' => $unitAfter,
            'line_total' => $lineBefore,
            'line_total_before_tax' => $lineBefore,
            'line_total_after_tax' => $lineAfter,
            'warranty_text' => '',
            'origin_text' => '',
            'specs_text' => $specs,
            'confidence_score' => 0.75,
            'field_confidence' => [],
            'warnings' => $warnings,
        ];
    }

    private function inferTaxPerUnitFromRawTax(?float $q, ?float $u, ?float $taxParsed): ?float
    {
        if ($taxParsed === null || $taxParsed <= 0 || $q === null || $q <= 0 || $u === null || $u < 0) {
            return null;
        }

        $tol = (float) config('quotation_ai.auto_correct.vat_per_unit_ratio_tolerance', 0.004);
        $base = $q * $u;
        if ($base <= 0) {
            return null;
        }

        $rateOnLine = $taxParsed / $base;
        if ($this->ratioMatchesStandardVnVat($rateOnLine, $tol)) {
            return round($u * $rateOnLine, 6);
        }

        $perUnit = $taxParsed / $q;
        $ratePerUnit = $perUnit / $u;
        if ($u > 0 && $this->ratioMatchesStandardVnVat($ratePerUnit, $tol)) {
            return round($perUnit, 6);
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

    private function amountsClose(float $a, float $b): bool
    {
        $rel = (float) config('quotation_ai.validation.line_total_relative_tolerance', 0.03);
        $abs = (float) config('quotation_ai.validation.line_total_absolute_tolerance', 100.0);

        return abs($a - $b) <= max($abs, $rel * max(abs($a), abs($b), 1.0));
    }

    private function parseAmount(string $value): ?float
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($s === '') {
            return null;
        }

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
