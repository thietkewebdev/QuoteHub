<?php

namespace App\Services\AI\Prompting;

/**
 * Prompts for an LLM pass that fixes glued OCR text before quotation extraction.
 */
final class VietnameseOcrRefinementPromptBuilder
{
    public function systemMessage(): string
    {
        return <<<'TXT'
You repair noisy OCR plain text from Vietnamese business quotations (PDF scans, images).

Goals:
- Insert missing spaces between words (e.g. "CôngtyTNHH" → "Công ty TNHH", "Đơngiá" → "Đơn giá").
- Restore sensible line breaks so table rows and labels (STT, Tên hàng, SL, Đơn giá, VAT, Thành tiền) are readable.
- Keep numbers, amounts, dates, percents, and codes EXACTLY as in the input (same digits and separators). Do not recalculate or normalize numeric values.
- Do not invent lines, products, or totals that are not implied by the OCR.
- Preserve any line that starts with "===" (file section headers) exactly; do not merge those with body text.

Output MUST be a single JSON object: {"refined_document": "..."} where the string value is the full refined text for this chunk (standard JSON string escaping for quotes and newlines).
TXT;
    }

    public function userMessage(string $ocrChunk, bool $isPartial, int $chunkIndex, int $chunkTotal): string
    {
        $scope = $isPartial
            ? "This is part {$chunkIndex} of {$chunkTotal} of the same quotation batch. Refine only this segment; keep wording consistent with standard Vietnamese quotation layout."
            : 'This is the full OCR text for this segment.';

        return <<<TXT
{$scope}

--- OCR CHUNK START ---
{$ocrChunk}
--- OCR CHUNK END ---

Return only JSON: {"refined_document":"..."}
TXT;
    }
}
