<?php

namespace App\Services\AI\Prompting;

use App\Models\IngestionBatch;
use App\Services\AI\SupplierExtraction\SupplierExtractionContext;

/**
 * Pass 2 — line items only, using pass-1 header context, supplier hints, and (when found) a table-only OCR slice.
 */
final class VietnameseQuotationPass2PromptBuilder
{
    public function systemMessage(): string
    {
        return <<<'PROMPT'
You extract ONLY product line items from Vietnamese supplier quotation OCR. Another pass already extracted the document header and totals — use that JSON as weak context; if OCR disagrees with the header block, trust OCR for line rows.

SUPPLIER PROFILE
- When the user message includes "COLUMN → FIELD MAPPING", treat it as the primary guide for mapping OCR table columns to JSON fields whenever the visible header row matches those labels.
- Even with strong mapping hints, never invent numeric values — each number must come from an OCR cell.

OUTPUT
- Return one JSON object only. No markdown.

LINE ITEMS
- One object per visible product row; renumber line_no 1..N in document order.
- raw_name: short product title from OCR (brand, model, connectivity) with normal Vietnamese spacing (e.g. "mã vạch", "tối đa"). Put long technical paragraphs (resolution, speed, barcode/QR support) in specs_text when possible.
- specs_text / warranty_text / origin_text: same spacing rule — readable Vietnamese, no glued words when the break is obvious from context.
- raw_model / brand: short codes from columns or tokens (often Latin); do not merge them into raw_name without a space when both appear.
- quantity, unit_price, vat_percent, tax_per_unit, unit_price_after_tax, line_total, line_total_before_tax, line_total_after_tax: JSON numbers or null; never concatenate adjacent number tokens (e.g. "63" and "4,150,000" stay separate).
- Prefer line_total = quantity × unit_price (pre-tax / "Thành tiền" before VAT) when the table shows it. If the document only shows a sau-thuế row total, put that in line_total_after_tax and leave line_total null if pre-tax is not visible.
- Four trailing numbers after product text often map to quantity, unit_price, per-line tax amount (VNĐ) or VAT %, then line total. When the tax column is money per unit (not %), put it in tax_per_unit; if only a % is shown, use vat_percent. If unsure, put the VND amount in warnings (e.g. "Thuế dòng (VNĐ): 332000") so post-processing can infer VAT.
- field_confidence per item: 0.0–1.0 for quantity, unit_price, line_total, raw_name, etc., based on OCR clarity.
- confidence_score per line: overall line quality 0.0–1.0.
- warnings: Vietnamese short notes for ambiguous cells.

TABLE RULES
- If a product table has multiple data rows, output multiple items — never collapse to one row unless the quotation truly has a single product line.

Required JSON shape:
{
  "items": [
    {
      "line_no": 1,
      "raw_name": "",
      "raw_model": "",
      "brand": "",
      "unit": "",
      "quantity": null,
      "unit_price": null,
      "vat_percent": null,
      "tax_per_unit": null,
      "unit_price_after_tax": null,
      "line_total": null,
      "line_total_before_tax": null,
      "line_total_after_tax": null,
      "warranty_text": "",
      "origin_text": "",
      "specs_text": "",
      "confidence_score": 0.0,
      "field_confidence": {},
      "warnings": []
    }
  ],
  "document_warnings": [],
  "overall_confidence": 0.0
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $pass1HeaderOnly
     * @param  array<string, mixed>  $tableSegmentationMeta
     */
    public function userMessage(
        IngestionBatch $batch,
        SupplierExtractionContext $supplierContext,
        array $pass1HeaderOnly,
        string $lineItemOcrSection,
        array $tableSegmentationMeta,
    ): string {
        $batch->loadMissing(['supplier']);
        $hints = [];
        $hints[] = 'PASS 2 / LINE ITEMS — Ingestion batch ID: '.$batch->getKey();
        $hints[] = 'Supplier extraction profile mode: '.$supplierContext->mode->value;
        if ($batch->supplier?->name) {
            $hints[] = 'Catalog supplier linked to this batch: '.$batch->supplier->name;
        }
        $block = (new SupplierProfilePromptFormatter)->guidanceBlock($supplierContext);
        if ($block !== null) {
            $hints[] = $block;
        }

        $headerJson = json_encode($pass1HeaderOnly, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($headerJson === false) {
            $headerJson = '{}';
        }
        $hints[] = "Pass-1 header JSON (context only; line rows must still match OCR):\n".$headerJson;

        $segNote = ($tableSegmentationMeta['used_full_document'] ?? true)
            ? 'OCR excerpt: full compiled document (no table-only slice was used).'
            : 'OCR excerpt: DETECTED PRODUCT-TABLE REGION ONLY (lines '.$tableSegmentationMeta['start_line_index'].'–'.$tableSegmentationMeta['end_line_index_exclusive'].', mode '.$tableSegmentationMeta['mode'].'). Header/footer text may be missing from this block — rely on Pass-1 header JSON for totals.';
        $hints[] = $segNote;

        $header = implode("\n\n", $hints);

        return <<<TXT
{$header}

Extract all product line items from the OCR below.

--- OCR START ---
{$lineItemOcrSection}
--- OCR END ---
TXT;
    }
}
