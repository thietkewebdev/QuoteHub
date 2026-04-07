<?php

namespace App\Support\SupplierExtraction;

use App\Models\SupplierExtractionProfile;

/**
 * Normalized view of the JSON `hints` column on {@see SupplierExtractionProfile}.
 *
 * @phpstan-type HintsArray array{
 *     header_patterns?: list<string>|mixed,
 *     table_column_order_hint?: string|mixed,
 *     keyword_aliases?: list<string>|mixed,
 *     contextual_phrases?: list<string>|mixed,
 *     vat_style_notes?: string|mixed,
 *     example_prompt_hints?: string|mixed,
 *     column_mapping_rules?: string|mixed,
 * }
 */
final readonly class SupplierProfileHintsBag
{
    /**
     * @param  list<string>  $headerPatterns
     * @param  list<string>  $keywordAliases
     * @param  list<string>  $contextualPhrases
     */
    public function __construct(
        public array $headerPatterns,
        public string $tableColumnOrderHint,
        public array $keywordAliases,
        public array $contextualPhrases,
        public string $vatStyleNotes,
        public string $examplePromptHints,
        /** Free-text rules mapping OCR column headers → JSON fields (supplier-specific). */
        public string $columnMappingRules,
    ) {}

    /**
     * @param  array<string, mixed>|null  $hints
     */
    public static function from(?array $hints): self
    {
        $h = $hints ?? [];

        return new self(
            headerPatterns: self::stringList($h['header_patterns'] ?? []),
            tableColumnOrderHint: trim((string) ($h['table_column_order_hint'] ?? '')),
            keywordAliases: self::stringList($h['keyword_aliases'] ?? []),
            contextualPhrases: self::stringList($h['contextual_phrases'] ?? []),
            vatStyleNotes: trim((string) ($h['vat_style_notes'] ?? '')),
            examplePromptHints: trim((string) ($h['example_prompt_hints'] ?? '')),
            columnMappingRules: trim((string) ($h['column_mapping_rules'] ?? '')),
        );
    }

    public function isEmpty(): bool
    {
        return $this->headerPatterns === []
            && $this->keywordAliases === []
            && $this->contextualPhrases === []
            && $this->tableColumnOrderHint === ''
            && $this->vatStyleNotes === ''
            && $this->examplePromptHints === ''
            && $this->columnMappingRules === '';
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }
}
