<?php

namespace App\Actions\Ingestion;

use App\Jobs\AI\RunBatchAiExtractionJob;
use App\Models\IngestionBatch;
use App\Models\User;
use App\Services\AI\QuotationExtractionService;
use App\Services\Operations\IngestionBatchRetryPolicy;
use Illuminate\Support\Facades\Log;

class RetryBatchAiExtractionAction
{
    /**
     * Re-run AI extraction using the existing {@see RunBatchAiExtractionJob} pipeline.
     * Persisted JSON is replaced via {@see QuotationExtractionService::extractAndPersist()} (updateOrCreate by batch).
     */
    public function execute(IngestionBatch $batch, User $user): void
    {
        $batch->loadMissing(['quotation', 'aiExtraction']);
        IngestionBatchRetryPolicy::ensureMayRetryAi($batch);

        Log::info('ingestion.ai.retry_queued', [
            'ingestion_batch_id' => $batch->id,
            'user_id' => $user->id,
            'prior_status' => $batch->status,
        ]);

        $batch->forceFill(['status' => 'ai_processing'])->save();

        RunBatchAiExtractionJob::dispatch($batch->id);
    }
}
