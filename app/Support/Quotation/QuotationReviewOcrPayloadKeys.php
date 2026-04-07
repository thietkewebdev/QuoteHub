<?php

namespace App\Support\Quotation;

/**
 * Keys written by Google OCR phase-1 capture; preserved when review form or AI overwrites payload_json.
 */
final class QuotationReviewOcrPayloadKeys
{
    /**
     * @var list<string>
     */
    public const PRESERVE_THROUGH_REVIEW_SAVE = [
        'source_file_path',
        'ocr_source_files',
        'ocr_provider',
        'ocr_processor_type',
        'raw_full_text',
        'raw_pages',
        'raw_blocks',
        'raw_tables',
        'extraction_status',
        'ocr_captured_at',
        'ocr_error',
    ];
}
