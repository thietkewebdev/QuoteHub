<?php

namespace App\Services\AI\Segmentation;

/**
 * Heuristically isolates the product-table band in full-document OCR for pass-2 line extraction.
 */
final class OcrTableRegionSegmenter
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function extractLineItemRegion(string $fullOcr): array
    {
        if (! (bool) config('quotation_ai.table_segmentation.enabled', true)) {
            return [$fullOcr, [
                'mode' => 'disabled',
                'used_full_document' => true,
            ]];
        }

        $lines = preg_split('/\R/u', $fullOcr) ?: [];
        if ($lines === []) {
            return [$fullOcr, ['mode' => 'empty', 'used_full_document' => true]];
        }

        $headerPattern = '/\b(STT|No\.|Tên\s*hàng|Tên\s*HH|Tên\s*SP|Đơn\s*giá|Thành\s*tiền|\bSL\b|Số\s*lượng|Qty|Quantity|VAT|Thuế|ĐVT)\b/iu';
        $footerPattern = '/^(Tổng|Tổng\s*cộng|Cộng\s*tiền|Grand\s*Total|Total\s*amount|Total\b|Subtotal|Cảm\s*ơn|Thank\s*you)/iu';

        $start = null;
        foreach ($lines as $i => $line) {
            if (preg_match($headerPattern, $line) === 1) {
                $start = (int) $i;
                break;
            }
        }

        if ($start === null) {
            return [$fullOcr, [
                'mode' => 'header_not_found',
                'used_full_document' => true,
            ]];
        }

        $end = count($lines);
        for ($j = $start + 1; $j < count($lines); $j++) {
            $trim = trim($lines[$j]);
            if ($trim === '') {
                continue;
            }
            if (preg_match($footerPattern, $trim) === 1) {
                $end = $j;
                break;
            }
        }

        $slice = array_slice($lines, $start, max(1, $end - $start));
        $region = implode("\n", $slice);
        $minLen = (int) config('quotation_ai.table_segmentation.min_region_chars', 40);
        if (mb_strlen(trim($region)) < $minLen) {
            return [$fullOcr, [
                'mode' => 'region_too_short',
                'used_full_document' => true,
            ]];
        }

        return [$region, [
            'mode' => 'segmented',
            'start_line_index' => $start,
            'end_line_index_exclusive' => $end,
            'used_full_document' => false,
        ]];
    }
}
