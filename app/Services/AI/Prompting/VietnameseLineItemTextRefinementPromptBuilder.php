<?php

namespace App\Services\AI\Prompting;

/**
 * Post-extraction pass: fix glued Vietnamese in line description fields only.
 */
final class VietnameseLineItemTextRefinementPromptBuilder
{
    public function systemMessage(): string
    {
        return <<<'TXT'
You fix spacing in Vietnamese commercial quotation line fields. The input is JSON with one object per line (line_no + text fields). Output JSON only.

Rules:
- Add missing spaces between Vietnamese words where OCR glued them. Real examples: "mãvạch" → "mã vạch"; "Độphân giải300dpi" → "Độ phân giải 300 dpi"; "tốiđa100mm/giây" → "tối đa 100 mm/giây"; "Hỗtrợinđa dạng" → "Hỗ trợ in đa dạng"; "rõràng" → "rõ ràng"; "xửlý" → "xử lý"; "Tốcđộin" → "Tốc độ in".
- Split product title vs technical specs: keep raw_name short — brand, model, interfaces (e.g. "Máy in mã vạch HPRT HT330 (USB+LAN+COM)"). Move long technical sentences (resolution, speed, barcode types, QR, warranty-like clauses) into specs_text. If specs_text already has content, append with a newline; do not duplicate the same sentence in both fields.
- Keep Latin model/SKU tokens intact (HT330, HPRT, USB, LAN, COM, dpi, 1D, 2D) but separate them from Vietnamese with spaces when needed (e.g. "HPRTHT330" → "HPRT HT330" only if clearly two tokens; prefer minimal change if ambiguous).
- Do NOT change numeric amounts, quantities, or arithmetic meaning. Do not insert/remove digits or commas in numbers.
- Do not invent product facts; only re-space existing characters.
- Preserve parentheses and punctuation; trim only outer whitespace on each field.

Return shape: {"items":[{"line_no":1,"raw_name":"...","specs_text":"...","warranty_text":"...","origin_text":"..."}, ...]}
Include every line_no from the input in the same order.
TXT;
    }

    /**
     * @param  list<array{line_no: int, raw_name: string, specs_text: string, warranty_text: string, origin_text: string}>  $payload
     */
    public function userMessage(array $payload): string
    {
        $json = json_encode(['items' => $payload], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"items":[]}';
        }

        return "Fix spacing in these line fields. Return JSON only.\n\n--- INPUT ---\n".$json."\n--- END ---";
    }
}
