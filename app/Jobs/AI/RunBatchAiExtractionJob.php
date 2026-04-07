<?php

namespace App\Jobs\AI;

use App\Models\IngestionBatch;
use App\Services\AI\QuotationExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunBatchAiExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $ingestionBatchId) {}

    public function handle(QuotationExtractionService $quotationExtractionService): void
    {
        $batch = IngestionBatch::query()->find($this->ingestionBatchId);

        if ($batch === null) {
            return;
        }

        if (! $batch->hasOcrResults()) {
            $batch->forceFill(['status' => 'ocr_done'])->save();

            return;
        }

        try {
            $quotationExtractionService->extractAndPersist($batch);
            $batch->forceFill(['status' => 'ai_done'])->save();
        } catch (Throwable $e) {
            Log::error('ingestion.ai_extraction.failed', [
                'ingestion_batch_id' => $batch->id,
                'message' => $e->getMessage(),
            ]);

            $batch->forceFill(['status' => 'ai_failed'])->save();

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        IngestionBatch::query()->whereKey($this->ingestionBatchId)->update(['status' => 'ai_failed']);
    }
}
