<?php

namespace App\Jobs\Ingestion;

use App\Models\IngestionBatch;
use App\Services\Ingestion\IngestionGoogleOcrDraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Seeds {@see QuotationReviewDraft} with synchronous Google OCR output without blocking the Filament create request.
 */
class CaptureGoogleOcrDraftForBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $ingestionBatchId) {}

    public function handle(IngestionGoogleOcrDraftService $service): void
    {
        if (! (bool) config('ingestion.google_ocr.enabled', true)) {
            return;
        }

        $batch = IngestionBatch::query()->with('files')->find($this->ingestionBatchId);

        if ($batch === null || $batch->file_count === 0) {
            return;
        }

        $service->captureForBatch($batch);
    }
}
