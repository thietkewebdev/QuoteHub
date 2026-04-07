<?php

namespace App\Services\Operations;

use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use InvalidArgumentException;

/**
 * Eligibility rules for operational OCR / AI retries (no approved quotation, safe batch state).
 */
final class IngestionBatchRetryPolicy
{
    /**
     * Offer “retry AI” when extraction confidence is below this (0–1 scale, same as stored column).
     */
    public const AI_LOW_CONFIDENCE_RETRY_THRESHOLD = 0.85;

    public static function hasApprovedQuotation(IngestionBatch $batch): bool
    {
        $batch->loadMissing('quotation');

        return $batch->quotation !== null
            && $batch->quotation->approved_at !== null;
    }

    public static function isLowConfidenceAiExtraction(?AiExtraction $extraction): bool
    {
        if ($extraction === null || $extraction->confidence_overall === null) {
            return false;
        }

        return (float) $extraction->confidence_overall < self::AI_LOW_CONFIDENCE_RETRY_THRESHOLD;
    }

    public static function canQueueOcrRetry(IngestionBatch $batch): bool
    {
        if (self::hasApprovedQuotation($batch)) {
            return false;
        }

        if ($batch->status === 'preprocessing') {
            return false;
        }

        if ((int) $batch->file_count === 0) {
            return false;
        }

        return IngestionBatchOperationalQuery::ocrConcernFileIds($batch) !== [];
    }

    public static function canQueueAiRetry(IngestionBatch $batch): bool
    {
        if (self::hasApprovedQuotation($batch)) {
            return false;
        }

        if ($batch->status === 'ai_processing') {
            return false;
        }

        if (! $batch->hasOcrResults()) {
            return false;
        }

        $batch->loadMissing('aiExtraction');

        return $batch->status === 'ai_failed'
            || self::isLowConfidenceAiExtraction($batch->aiExtraction);
    }

    /**
     * @param  list<int>  $ocrRetryFileIds  From {@see IngestionBatchOperationalQuery::ocrConcernFileIds()}
     */
    public static function ensureMayRetryOcr(IngestionBatch $batch, array $ocrRetryFileIds): void
    {
        if (self::hasApprovedQuotation($batch)) {
            throw new InvalidArgumentException(__('Cannot retry OCR: this batch has an approved quotation.'));
        }

        if ($batch->status === 'preprocessing') {
            throw new InvalidArgumentException(__('OCR is already running for this batch.'));
        }

        if ((int) $batch->file_count === 0) {
            throw new InvalidArgumentException(__('This batch has no files.'));
        }

        if ($ocrRetryFileIds === []) {
            throw new InvalidArgumentException(__('No OCR-eligible files match the retry criteria for this batch.'));
        }
    }

    public static function ensureMayRetryAi(IngestionBatch $batch): void
    {
        if (self::hasApprovedQuotation($batch)) {
            throw new InvalidArgumentException(__('Cannot retry AI extraction: this batch has an approved quotation.'));
        }

        if ($batch->status === 'ai_processing') {
            throw new InvalidArgumentException(__('AI extraction is already running for this batch.'));
        }

        if (! $batch->hasOcrResults()) {
            throw new InvalidArgumentException(__('Cannot retry AI extraction: no usable Google structured OCR is available yet.'));
        }

        $batch->loadMissing('aiExtraction');

        $allowed = $batch->status === 'ai_failed'
            || self::isLowConfidenceAiExtraction($batch->aiExtraction);

        if (! $allowed) {
            throw new InvalidArgumentException(__('AI retry is only available for failed runs or low-confidence extractions.'));
        }
    }
}
