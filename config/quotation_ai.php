<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR text refinement (LLM, before extraction)
    |--------------------------------------------------------------------------
    | Disabled by default: quotation extraction compiles input from Google OCR
    | structured output only; LLM must not rewrite OCR text before extraction.
    */
    'ocr_refinement' => [
        'enabled' => filter_var(env('QUOTATION_AI_OCR_REFINEMENT', false), FILTER_VALIDATE_BOOL),
        'model' => ($m = env('QUOTATION_AI_OCR_REFINEMENT_MODEL')) !== null && $m !== ''
            ? (string) $m
            : null,
        'max_chars_per_chunk' => (int) env('QUOTATION_AI_OCR_REFINEMENT_MAX_CHARS', 12_000),
        'timeout' => ($t = env('QUOTATION_AI_OCR_REFINEMENT_TIMEOUT')) !== null && $t !== ''
            ? (int) $t
            : null,
        'temperature' => ($tmp = env('QUOTATION_AI_OCR_REFINEMENT_TEMPERATURE')) !== null && $tmp !== ''
            ? (float) $tmp
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Line item text (post-extraction)
    |--------------------------------------------------------------------------
    | Regex fixes letter↔digit boundaries; optional LLM pass fixes glued Vietnamese
    | in raw_name / specs_text / warranty / origin (fixes UI "dính chữ").
    */
    'line_text_refinement' => [
        'regex_letter_digit' => filter_var(env('QUOTATION_AI_LINE_TEXT_REGEX', true), FILTER_VALIDATE_BOOL),
        'glue_phrases_enabled' => filter_var(env('QUOTATION_AI_LINE_TEXT_GLUE_MAP', true), FILTER_VALIDATE_BOOL),
        /*
         * Longer keys first is enforced at runtime; extend for your suppliers’ OCR quirks.
         */
        'glue_phrases' => [
            'Hỗtrợinđa dạng' => 'Hỗ trợ in đa dạng',
            'Hỗtrợinđa' => 'Hỗ trợ in đa',
            'Tốcđộin' => 'Tốc độ in',
            'Độphân giải' => 'Độ phân giải',
            'Độphân' => 'Độ phân',
            'mãvạch1D' => 'mã vạch 1D',
            'mãvạch' => 'mã vạch',
            'rõràng' => 'rõ ràng',
            'tốiđa' => 'tối đa',
            'xửlý' => 'xử lý',
            'thờigian' => 'thời gian',
            'tiếtkiệm' => 'tiết kiệm',
        ],
        'llm_enabled' => filter_var(env('QUOTATION_AI_LINE_TEXT_LLM', true), FILTER_VALIDATE_BOOL),
        'model' => ($m = env('QUOTATION_AI_LINE_TEXT_MODEL')) !== null && $m !== ''
            ? (string) $m
            : null,
        'max_field_chars' => (int) env('QUOTATION_AI_LINE_TEXT_MAX_FIELD_CHARS', 4000),
        'timeout' => ($t = env('QUOTATION_AI_LINE_TEXT_TIMEOUT')) !== null && $t !== ''
            ? (int) $t
            : null,
        'temperature' => ($tmp = env('QUOTATION_AI_LINE_TEXT_TEMPERATURE')) !== null && $tmp !== ''
            ? (float) $tmp
            : null,
        /** Move technical tail (after ") " + spec cues) from raw_name into specs_text */
        'product_specs_split' => filter_var(env('QUOTATION_AI_PRODUCT_SPECS_SPLIT', true), FILTER_VALIDATE_BOOL),
        'product_specs_split_min_length' => (int) env('QUOTATION_AI_PRODUCT_SPECS_SPLIT_MIN_LEN', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extraction driver
    |--------------------------------------------------------------------------
    | "openai" — OCR text → structured JSON via OpenAI Chat Completions
    | "mock" — no API calls; empty schema for tests / local without a key
    */
    'driver' => env('QUOTATION_AI_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Extraction pipeline mode
    |--------------------------------------------------------------------------
    | llm_table — OCR text → full-document LLM extraction (legacy two-pass / single-pass)
    | hybrid    — Parser/table assembly first; LLM normalizes rows only; deterministic VAT pass
    */
    'pipeline' => [
        'mode' => strtolower((string) env('QUOTATION_EXTRACTION_PIPELINE', 'llm_table')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Review draft (Filament) vs AI extraction_json
    |--------------------------------------------------------------------------
    | When true, after any successful AI extraction the review draft is overwritten
    | from extraction_json (OCR capture keys preserved). Also used when opening
    | review if the draft row predates the latest AiExtraction (e.g. llm_table runs
    | without a prior hybrid seed).
    */
    'review_draft' => [
        'seed_from_ai_extraction' => filter_var(
            ($e = env('QUOTATION_REVIEW_DRAFT_SEED_FROM_AI')) !== null && $e !== ''
                ? $e
                : env('QUOTATION_HYBRID_SEED_REVIEW_DRAFT', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'hybrid' => [
        'model_label' => env('QUOTATION_HYBRID_MODEL_LABEL', 'hybrid-v1'),
        'prompt_version' => env('QUOTATION_HYBRID_PROMPT_VERSION', 'hybrid-v1-row-normalizer'),
        'llm_row_normalizer_enabled' => filter_var(env('QUOTATION_HYBRID_ROW_LLM', true), FILTER_VALIDATE_BOOL),
        'row_normalizer_model' => ($m = env('QUOTATION_HYBRID_ROW_MODEL')) !== null && $m !== ''
            ? (string) $m
            : null,
        'header_snippet_max_chars' => (int) env('QUOTATION_HYBRID_HEADER_SNIPPET_CHARS', 4000),
        'base_confidence' => (float) env('QUOTATION_HYBRID_BASE_CONFIDENCE', 0.72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stored on ai_extractions.prompt_version
    |--------------------------------------------------------------------------
    */
    'prompt_version' => env('QUOTATION_PROMPT_VERSION', 'v8-two-pass-validation'),

    /*
    |--------------------------------------------------------------------------
    | Extraction engine
    |--------------------------------------------------------------------------
    | v2 — two OpenAI passes (header, then lines) + post-validation
    | v1 — legacy single pass (rollback)
    */
    'extraction_engine' => [
        'version' => strtolower((string) env('QUOTATION_AI_EXTRACTION_ENGINE', 'v2')),
        'pass1_confidence_weight' => (float) env('QUOTATION_AI_PASS1_WEIGHT', 0.4),
        'pass2_confidence_weight' => (float) env('QUOTATION_AI_PASS2_WEIGHT', 0.6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pass-2 OCR table segmentation
    |--------------------------------------------------------------------------
    */
    'table_segmentation' => [
        'enabled' => filter_var(env('QUOTATION_AI_TABLE_SEGMENTATION', true), FILTER_VALIDATE_BOOL),
        'min_region_chars' => (int) env('QUOTATION_AI_TABLE_MIN_CHARS', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-correction (before validation; numeric fields only)
    |--------------------------------------------------------------------------
    */
    'auto_correct' => [
        'enabled' => filter_var(env('QUOTATION_AI_AUTO_CORRECT', true), FILTER_VALIDATE_BOOL),
        'split_match_relative_tolerance' => (float) env('QUOTATION_AI_AUTOCORRECT_SPLIT_REL', 0.03),
        'split_match_absolute_tolerance' => (float) env('QUOTATION_AI_AUTOCORRECT_SPLIT_ABS', 500),
        'vat_percent_max_plausible' => (float) env('QUOTATION_AI_VAT_PERCENT_MAX', 100),
        'confidence_recovery_per_fix' => (float) env('QUOTATION_AI_AUTOCORRECT_CONF_RECOVERY', 0.025),
        /** tax_per_unit / unit_price must be within this absolute delta of 0.08 or 0.10 */
        'vat_per_unit_ratio_tolerance' => (float) env('QUOTATION_AI_VAT_PER_UNIT_RATIO_TOL', 0.004),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extraction history (per batch attempts)
    |--------------------------------------------------------------------------
    */
    'history' => [
        'enabled' => filter_var(env('QUOTATION_AI_EXTRACTION_HISTORY', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-extraction validation (deterministic)
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'enabled' => filter_var(env('QUOTATION_AI_VALIDATION_ENABLED', true), FILTER_VALIDATE_BOOL),
        'line_total_relative_tolerance' => (float) env('QUOTATION_AI_LINE_REL_TOLERANCE', 0.03),
        'line_total_absolute_tolerance' => (float) env('QUOTATION_AI_LINE_ABS_TOLERANCE', 100),
        'sum_lines_relative_tolerance' => (float) env('QUOTATION_AI_SUM_REL_TOLERANCE', 0.04),
        'sum_lines_absolute_tolerance' => (float) env('QUOTATION_AI_SUM_ABS_TOLERANCE', 5000),
        'suspicious_quantity_min' => (float) env('QUOTATION_AI_SUSPICIOUS_QTY_MIN', 1_000_000),
        'confidence_penalty_line_mismatch' => (float) env('QUOTATION_AI_PENALTY_LINE', 0.92),
        'confidence_penalty_sum_mismatch' => (float) env('QUOTATION_AI_PENALTY_SUM', 0.88),
        'confidence_penalty_suspicious_merge' => (float) env('QUOTATION_AI_PENALTY_MERGE', 0.88),
        'confidence_penalty_field' => (float) env('QUOTATION_AI_PENALTY_FIELD', 0.9),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (driver: openai)
    |--------------------------------------------------------------------------
    | OPENAI_MODEL is the primary env var; OPENAI_QUOTATION_MODEL is a legacy alias.
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', env('OPENAI_QUOTATION_MODEL', 'gpt-5-mini')),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
        /*
         * Omit from API request when null (required for some models e.g. gpt-5-mini that only allow default temperature).
         * Set OPENAI_QUOTATION_TEMPERATURE=0.1 for models that accept it (e.g. gpt-4o).
         */
        'temperature' => ($t = env('OPENAI_QUOTATION_TEMPERATURE')) !== null && $t !== ''
            ? (float) $t
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier-specific extraction hints (OCR → AI)
    |--------------------------------------------------------------------------
    | When an ingestion batch has no catalog supplier_id, we may infer a supplier
    | by matching OCR text against enabled profiles (names, aliases, patterns).
    | Scores are internal weights — not calibrated probabilities; do not use them
    | to inflate model confidence.
    */
    'supplier_inference' => [
        'enabled' => filter_var(env('QUOTATION_AI_SUPPLIER_INFERENCE', true), FILTER_VALIDATE_BOOL),
        'min_score' => (float) env('QUOTATION_AI_SUPPLIER_INFERENCE_MIN_SCORE', 2.5),
        'min_supplier_name_length' => (int) env('QUOTATION_AI_SUPPLIER_INFERENCE_MIN_NAME_LEN', 3),
        'max_alias_matches' => (int) env('QUOTATION_AI_SUPPLIER_INFERENCE_MAX_ALIASES', 4),
        'max_header_pattern_matches' => (int) env('QUOTATION_AI_SUPPLIER_INFERENCE_MAX_HEADERS', 6),
        'max_contextual_matches' => (int) env('QUOTATION_AI_SUPPLIER_INFERENCE_MAX_PHRASES', 4),
        'weights' => [
            'supplier_name' => (float) env('QUOTATION_AI_SUPPLIER_WEIGHT_NAME', 3.0),
            'keyword_alias' => (float) env('QUOTATION_AI_SUPPLIER_WEIGHT_ALIAS', 2.0),
            'header_pattern' => (float) env('QUOTATION_AI_SUPPLIER_WEIGHT_HEADER', 1.25),
            'contextual_phrase' => (float) env('QUOTATION_AI_SUPPLIER_WEIGHT_PHRASE', 1.5),
        ],
    ],

];
