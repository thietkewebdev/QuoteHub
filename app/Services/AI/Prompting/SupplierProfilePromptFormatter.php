<?php

namespace App\Services\AI\Prompting;

use App\Services\AI\SupplierExtraction\SupplierExtractionContext;
use App\Support\SupplierExtraction\SupplierProfileApplicationMode;
use App\Support\SupplierExtraction\SupplierProfileHintsBag;

/**
 * Shared supplier-profile text for extraction prompts (v1 single-pass and v2 passes).
 */
final class SupplierProfilePromptFormatter
{
    public function guidanceBlock(SupplierExtractionContext $context): ?string
    {
        if ($context->mode === SupplierProfileApplicationMode::None) {
            return null;
        }

        $lines = [];
        $lines[] = '=== Supplier-specific extraction profile ===';
        $lines[] = 'When OCR table headers clearly match this supplier’s layout rules, treat column→field mapping below as high-priority guidance. You must still copy numeric cell values from OCR only — never invent prices, quantities, or line totals.';

        if ($context->mode === SupplierProfileApplicationMode::Confirmed) {
            $lines[] = 'Profile source: CONFIRMED — catalog supplier linked to this batch; mapping rules are especially likely to apply when headers match.';
        } elseif ($context->mode === SupplierProfileApplicationMode::Inferred) {
            $lines[] = 'Profile source: INFERRED — supplier was guessed from OCR (may be wrong). If headers do not resemble this supplier’s layout, ignore mapping rules.';
            if ($context->inferenceRawScore !== null) {
                $lines[] = 'Internal inference score (for staff; not a probability): '.round($context->inferenceRawScore, 3);
            }
            if ($context->supplierInferenceConfidence !== null) {
                $lines[] = 'Normalized inference confidence (0–1, heuristic): '.round($context->supplierInferenceConfidence, 3);
            }
            if ($context->matchedTerms !== []) {
                $lines[] = 'Matched OCR signals: '.implode('; ', $context->matchedTerms);
            }
        }

        $profile = $context->profile;
        if ($profile === null) {
            $lines[] = 'No structured extraction profile is stored for this supplier — use OCR and general rules only (besides any catalog supplier line above).';

            return implode("\n", $lines);
        }

        $bag = $profile->hintsBag();
        if ($bag->isEmpty()) {
            $lines[] = 'Extraction profile exists but has no saved hints yet.';

            return implode("\n", $lines);
        }

        $lines = array_merge($lines, $this->formatHintsForPrompt($bag));

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function formatHintsForPrompt(SupplierProfileHintsBag $bag): array
    {
        $out = [];

        if ($bag->columnMappingRules !== '') {
            $out[] = 'COLUMN → FIELD MAPPING (supplier-specific; use when OCR header row matches these labels): '.$bag->columnMappingRules;
        }

        if ($bag->headerPatterns !== []) {
            $out[] = 'Common header / title fragments (may appear on this supplier’s PDFs): '.implode(' | ', $bag->headerPatterns);
        }

        if ($bag->tableColumnOrderHint !== '') {
            $out[] = 'Typical table column order / layout notes: '.$bag->tableColumnOrderHint;
        }

        if ($bag->keywordAliases !== []) {
            $out[] = 'Known supplier name variants or aliases in OCR: '.implode(' | ', $bag->keywordAliases);
        }

        if ($bag->contextualPhrases !== []) {
            $out[] = 'Other phrases that often appear on this supplier’s documents: '.implode(' | ', $bag->contextualPhrases);
        }

        if ($bag->vatStyleNotes !== '') {
            $out[] = 'VAT / tax presentation notes: '.$bag->vatStyleNotes;
        }

        if ($bag->examplePromptHints !== '') {
            $out[] = 'Parsing cautions / examples for staff-tuned guidance: '.$bag->examplePromptHints;
        }

        return $out;
    }
}
