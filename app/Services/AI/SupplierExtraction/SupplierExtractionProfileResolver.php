<?php

namespace App\Services\AI\SupplierExtraction;

use App\Models\IngestionBatch;
use App\Models\SupplierExtractionProfile;
use App\Support\SupplierExtraction\SupplierProfileApplicationMode;

/**
 * Decides whether extraction prompts use a supplier-specific profile (confirmed vs inferred).
 *
 * Flow:
 * 1. If the batch already has {@see IngestionBatch::$supplier_id}, mode = confirmed; attach that supplier's enabled profile when present.
 * 2. Else if inference is enabled, score enabled profiles against lowercased OCR using weighted substring matches (name, aliases, header patterns, contextual phrases).
 * 3. If the best score ≥ configured minimum, mode = inferred; otherwise none.
 */
final class SupplierExtractionProfileResolver
{
    public function resolve(IngestionBatch $batch, string $ocrDocumentText): SupplierExtractionContext
    {
        $batch->loadMissing(['supplier']);

        if ($batch->supplier_id) {
            $profile = SupplierExtractionProfile::query()
                ->where('supplier_id', $batch->supplier_id)
                ->where('is_enabled', true)
                ->with(['supplier'])
                ->first();

            return new SupplierExtractionContext(
                mode: SupplierProfileApplicationMode::Confirmed,
                supplierId: (int) $batch->supplier_id,
                profile: $profile,
                inferenceRawScore: null,
                supplierInferenceConfidence: 1.0,
                matchedTerms: [],
            );
        }

        if (! (bool) config('quotation_ai.supplier_inference.enabled', true)) {
            return new SupplierExtractionContext(
                mode: SupplierProfileApplicationMode::None,
                supplierId: null,
                profile: null,
                inferenceRawScore: null,
                supplierInferenceConfidence: null,
                matchedTerms: [],
            );
        }

        $haystack = mb_strtolower($ocrDocumentText, 'UTF-8');
        $minScore = (float) config('quotation_ai.supplier_inference.min_score', 2.5);
        $weights = (array) config('quotation_ai.supplier_inference.weights', []);
        $wName = (float) ($weights['supplier_name'] ?? 3.0);
        $wAlias = (float) ($weights['keyword_alias'] ?? 2.0);
        $wHeader = (float) ($weights['header_pattern'] ?? 1.25);
        $wPhrase = (float) ($weights['contextual_phrase'] ?? 1.5);

        $maxAliases = (int) config('quotation_ai.supplier_inference.max_alias_matches', 4);
        $maxHeaders = (int) config('quotation_ai.supplier_inference.max_header_pattern_matches', 6);
        $maxPhrases = (int) config('quotation_ai.supplier_inference.max_contextual_matches', 4);
        $minNameLen = (int) config('quotation_ai.supplier_inference.min_supplier_name_length', 3);

        $theoreticalMax = $this->theoreticalMaxScore(
            $wName,
            $wAlias,
            $wHeader,
            $wPhrase,
            $maxAliases,
            $maxHeaders,
            $maxPhrases,
        );

        $bestProfile = null;
        $bestScore = 0.0;
        $bestTerms = [];

        $profiles = SupplierExtractionProfile::query()
            ->where('is_enabled', true)
            ->with(['supplier'])
            ->get();

        foreach ($profiles as $profile) {
            [$score, $terms] = $this->scoreProfile(
                $profile,
                $haystack,
                wName: $wName,
                wAlias: $wAlias,
                wHeader: $wHeader,
                wPhrase: $wPhrase,
                maxAliases: $maxAliases,
                maxHeaders: $maxHeaders,
                maxPhrases: $maxPhrases,
                minNameLen: $minNameLen,
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProfile = $profile;
                $bestTerms = $terms;
            }
        }

        if ($bestProfile !== null && $bestScore >= $minScore) {
            $confidence = $theoreticalMax > 0 ? min(1.0, $bestScore / $theoreticalMax) : 0.0;

            return new SupplierExtractionContext(
                mode: SupplierProfileApplicationMode::Inferred,
                supplierId: (int) $bestProfile->supplier_id,
                profile: $bestProfile,
                inferenceRawScore: $bestScore,
                supplierInferenceConfidence: $confidence,
                matchedTerms: $bestTerms,
            );
        }

        return new SupplierExtractionContext(
            mode: SupplierProfileApplicationMode::None,
            supplierId: null,
            profile: null,
            inferenceRawScore: null,
            supplierInferenceConfidence: null,
            matchedTerms: [],
        );
    }

    private function theoreticalMaxScore(
        float $wName,
        float $wAlias,
        float $wHeader,
        float $wPhrase,
        int $maxAliases,
        int $maxHeaders,
        int $maxPhrases,
    ): float {
        return $wName + $maxAliases * $wAlias + $maxHeaders * $wHeader + $maxPhrases * $wPhrase;
    }

    /**
     * @return array{0: float, 1: list<string>}
     */
    private function scoreProfile(
        SupplierExtractionProfile $profile,
        string $haystack,
        float $wName,
        float $wAlias,
        float $wHeader,
        float $wPhrase,
        int $maxAliases,
        int $maxHeaders,
        int $maxPhrases,
        int $minNameLen,
    ): array {
        $terms = [];
        $score = 0.0;

        $supplierName = trim((string) ($profile->supplier?->name ?? ''));
        $nameLower = mb_strtolower($supplierName, 'UTF-8');
        if ($nameLower !== '' && mb_strlen($supplierName, 'UTF-8') >= $minNameLen && str_contains($haystack, $nameLower)) {
            $score += $wName;
            $terms[] = $supplierName;
        }

        $bag = $profile->hintsBag();

        [$aliasScore, $aliasTerms] = $this->collectWeightedMatches(
            $bag->keywordAliases,
            $haystack,
            $wAlias,
            $maxAliases,
        );
        $score += $aliasScore;
        array_push($terms, ...$aliasTerms);

        [$headerScore, $headerTerms] = $this->collectWeightedMatches(
            $bag->headerPatterns,
            $haystack,
            $wHeader,
            $maxHeaders,
        );
        $score += $headerScore;
        array_push($terms, ...$headerTerms);

        [$phraseScore, $phraseTerms] = $this->collectWeightedMatches(
            $bag->contextualPhrases,
            $haystack,
            $wPhrase,
            $maxPhrases,
        );
        $score += $phraseScore;
        array_push($terms, ...$phraseTerms);

        return [$score, array_values(array_unique($terms))];
    }

    /**
     * @param  list<string>  $needles
     * @return array{0: float, 1: list<string>}
     */
    private function collectWeightedMatches(array $needles, string $haystack, float $weightEach, int $maxMatches): array
    {
        $matched = [];
        foreach ($needles as $needle) {
            if (count($matched) >= $maxMatches) {
                break;
            }
            $n = mb_strtolower(trim($needle), 'UTF-8');
            if ($n === '' || mb_strlen($n, 'UTF-8') < 2) {
                continue;
            }
            if (str_contains($haystack, $n)) {
                $matched[] = $needle;
            }
        }

        return [count($matched) * $weightEach, $matched];
    }
}
