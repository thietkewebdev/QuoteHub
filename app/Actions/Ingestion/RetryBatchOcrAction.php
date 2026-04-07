<?php

namespace App\Actions\Ingestion;

use App\Models\IngestionBatch;
use App\Models\User;
use App\Services\Operations\IngestionBatchOperationalQuery;
use App\Services\Operations\IngestionBatchRetryPolicy;
use Illuminate\Support\Facades\Log;

class RetryBatchOcrAction
{
    public function __construct(
        protected DispatchBatchOcrJobsAction $dispatchBatchOcrJobs,
    ) {}

    /**
     * Clear OCR rows for flagged files, set batch to preprocessing, and queue OCR jobs for those files only.
     *
     * @return int Number of OCR jobs queued
     */
    public function execute(IngestionBatch $batch, User $user): int
    {
        $batch->loadMissing(['quotation']);

        $fileIds = IngestionBatchOperationalQuery::ocrConcernFileIds($batch);
        IngestionBatchRetryPolicy::ensureMayRetryOcr($batch, $fileIds);

        Log::info('ingestion.ocr.retry_queued', [
            'ingestion_batch_id' => $batch->id,
            'user_id' => $user->id,
            'ingestion_file_ids' => $fileIds,
        ]);

        return $this->dispatchBatchOcrJobs->executeForIngestionFileIds(
            $batch,
            $fileIds,
            clearOcrResultsFirst: true,
        );
    }
}
