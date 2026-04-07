<?php

namespace App\Services\AI\Prompting;

use App\Models\IngestionBatch;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;

/**
 * Pass 1 — header, totals, and header field confidence only (no line items).
 */
final class VietnameseQuotationPass1PromptBuilder
{
    public function systemMessage(): string
    {
        return <<<'PROMPT'
You extract ONLY the quotation header and monetary totals from Vietnamese supplier quotation OCR. Do not extract product line items in this pass.

OUTPUT
- Return one JSON object only. No markdown.

LANGUAGE
- Keep human-readable strings in the same language/script as OCR (Vietnamese stays Vietnamese).

HEADER FIELDS
- supplier_name, supplier_quote_number, quote_date, valid_until: from OCR only.
- currency: "VND" when đ/₫/VNĐ clearly applies; else "" if unclear.
- subtotal_before_tax, tax_amount, total_amount: JSON numbers only; null if not explicitly labeled in the document.
- tax_amount: only total-document tax rows, not per-line tax.
- total_amount: labeled grand totals ("Tổng tiền", "Tổng cộng", etc.).
- contact_person, notes: short OCR fragments; notes may mention OCR quality.

FIELD CONFIDENCE
- quotation_header.field_confidence: for each header business field above (except field_confidence itself), a number 0.0–1.0 for how clearly OCR supports that value.

OVERALL
- overall_confidence: 0.0–1.0 for this pass only (header evidence).

SUPPLIER HINTS in the user message are non-binding soft guidance; still ground every value in OCR.

Required JSON shape:
{
  "quotation_header": {
    "supplier_name": "",
    "supplier_quote_number": "",
    "quote_date": "",
    "valid_until": "",
    "currency": "VND",
    "subtotal_before_tax": null,
    "tax_amount": null,
    "total_amount": null,
    "contact_person": "",
    "notes": "",
    "field_confidence": {}
  },
  "document_warnings": [],
  "overall_confidence": 0.0
}
PROMPT;
    }

    public function userMessage(string $ocrDocumentText, IngestionBatch $batch, SupplierExtractionContext $supplierContext): string
    {
        $batch->loadMissing(['supplier']);
        $hints = [];
        $hints[] = 'PASS 1 / HEADER ONLY — Ingestion batch ID: '.$batch->getKey();
        $hints[] = 'Supplier extraction profile mode: '.$supplierContext->mode->value;
        if ($batch->supplier?->name) {
            $hints[] = 'Catalog supplier linked to this batch (use ONLY if the same name or clear variant appears in the OCR; otherwise leave supplier_name empty): '.$batch->supplier->name;
        }
        $block = (new SupplierProfilePromptFormatter)->guidanceBlock($supplierContext);
        if ($block !== null) {
            $hints[] = $block;
        }
        $header = implode("\n", $hints);

        return <<<TXT
{$header}

Extract the header and totals from the OCR below. Ignore product tables for now.

--- OCR START ---
{$ocrDocumentText}
--- OCR END ---
TXT;
    }
}
