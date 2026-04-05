<?php

namespace App\Actions\Ingestion;

use App\Jobs\OCR\RunOcrForFileJob;
use App\Models\IngestionBatch;

class DispatchBatchOcrJobsAction
{
    /**
     * Dispatch one stub OCR job per ingestion file in the batch.
     */
    public function execute(IngestionBatch $batch): int
    {
        $count = 0;

        foreach ($batch->files()->orderBy('page_order')->cursor() as $file) {
            RunOcrForFileJob::dispatch($file);
            $count++;
        }

        return $count;
    }
}
