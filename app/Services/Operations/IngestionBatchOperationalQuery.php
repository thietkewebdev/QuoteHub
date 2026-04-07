<?php

namespace App\Services\Operations;

use App\Models\IngestionBatch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only query helpers for operational / exception views (ingestion batches).
 */
final class IngestionBatchOperationalQuery
{
    /**
     * OCR “partial” threshold: below this confidence, a file counts as problematic (aligned with monitoring).
     */
    public const OCR_CONCERN_CONFIDENCE_THRESHOLD = 0.45;

    /**
     * Batches where at least one OCR-eligible file has missing OCR, empty text, or low OCR confidence.
     */
    public static function ocrConcern(Builder $query, ?float $lowConfidenceThreshold = null): Builder
    {
        $lowConfidenceThreshold ??= self::OCR_CONCERN_CONFIDENCE_THRESHOLD;

        return $query
            ->whereNotIn('ingestion_batches.status', ['pending', 'uploaded'])
            ->whereHas('files', function (Builder $files) use ($lowConfidenceThreshold): void {
                self::scopeFilesWithOcrConcern($files, $lowConfidenceThreshold);
            });
    }

    /**
     * Ingestion file IDs for this batch that are OCR-eligible and match the OCR concern rules.
     *
     * @return list<int>
     */
    public static function ocrConcernFileIds(IngestionBatch $batch, ?float $lowConfidenceThreshold = null): array
    {
        $lowConfidenceThreshold ??= self::OCR_CONCERN_CONFIDENCE_THRESHOLD;

        $q = $batch->files()->getQuery();
        self::scopeFilesWithOcrConcern($q, $lowConfidenceThreshold);

        return $q
            ->orderBy('page_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Limit a files query to OCR-eligible MIME types and OCR concern (missing / empty / low confidence).
     */
    public static function scopeFilesWithOcrConcern(Builder $files, float $lowConfidenceThreshold): void
    {
        $files
            ->where(function (Builder $m): void {
                $m->where('mime_type', 'like', 'image/%')
                    ->orWhere('mime_type', 'application/pdf');
            })
            ->where(function (Builder $issue) use ($lowConfidenceThreshold): void {
                $issue
                    ->whereDoesntHave('ocrResults')
                    ->orWhereHas('ocrResults', function (Builder $ocr) use ($lowConfidenceThreshold): void {
                        $ocr->where(function (Builder $inner) use ($lowConfidenceThreshold): void {
                            $inner
                                ->whereNotIn('engine_name', ['google-document-ai', 'google-vision'])
                                ->orWhere(function (Builder $googleBad) use ($lowConfidenceThreshold): void {
                                    $googleBad
                                        ->whereIn('engine_name', ['google-document-ai', 'google-vision'])
                                        ->where(function (Builder $b) use ($lowConfidenceThreshold): void {
                                            $b->where(function (Builder $empty): void {
                                                $empty->whereNull('raw_text')
                                                    ->orWhereRaw("TRIM(COALESCE(raw_text, '')) = ''");
                                            })
                                                ->orWhere(function (Builder $c) use ($lowConfidenceThreshold): void {
                                                    $c->whereNotNull('confidence')
                                                        ->where('confidence', '<', $lowConfidenceThreshold);
                                                });
                                        });
                                });
                        });
                    });
            });
    }

    public static function base(): Builder
    {
        return IngestionBatch::query()->with(['supplier']);
    }
}
