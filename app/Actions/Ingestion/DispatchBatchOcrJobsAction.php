<?php

namespace App\Actions\Ingestion;

use App\Jobs\AI\RunBatchAiExtractionJob;
use App\Jobs\OCR\RunOcrForFileJob;
use App\Models\IngestionBatch;
use App\Models\IngestionFile;
use App\Models\OcrResult;
use App\Services\OCR\GoogleOcrStructuredDocumentCompiler;
use App\Services\OCR\OcrExtractionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class DispatchBatchOcrJobsAction
{
    public function __construct(
        protected OcrExtractionService $ocrExtractionService,
    ) {}

    /**
     * Queue OCR for every file in the batch (unsupported types are skipped by jobs).
     */
    public function execute(IngestionBatch $batch): int
    {
        $ids = $batch->files()->pluck('id')->all();

        return $this->executeForIngestionFileIds($batch, $ids, clearOcrResultsFirst: false, queueAiExtractionAfterOcr: false);
    }

    /**
     * Queue OCR for all files; when the OCR job batch finishes, queue AI extraction if Google structured OCR is available.
     * If there is nothing to OCR but structured OCR already exists, queues AI immediately.
     *
     * @return array{ocr_jobs: int, ai_queued: bool, ai_immediate: bool, idle_reason?: string}
     */
    public function executeThenQueueAi(IngestionBatch $batch): array
    {
        $ids = $batch->files()->pluck('id')->all();
        $ocrJobs = $this->executeForIngestionFileIds($batch, $ids, clearOcrResultsFirst: false, queueAiExtractionAfterOcr: true);

        if ($ocrJobs > 0) {
            return ['ocr_jobs' => $ocrJobs, 'ai_queued' => true, 'ai_immediate' => false];
        }

        $batch->refresh();

        if (! $batch->hasOcrResults()) {
            return [
                'ocr_jobs' => 0,
                'ai_queued' => false,
                'ai_immediate' => false,
                'idle_reason' => $this->resolveOcrIdleReason($batch),
            ];
        }

        $batch->forceFill(['status' => 'ai_processing'])->save();
        RunBatchAiExtractionJob::dispatch($batch->id);

        return ['ocr_jobs' => 0, 'ai_queued' => true, 'ai_immediate' => true];
    }

    /**
     * Why “Run OCR & AI” could not queue OCR or reuse structured Google OCR (for staff messaging).
     */
    private function resolveOcrIdleReason(IngestionBatch $batch): string
    {
        $fileCount = $batch->files()->count();

        if ($fileCount === 0) {
            return 'no_files';
        }

        $ocrEligible = $batch->files()
            ->get()
            ->filter(fn (IngestionFile $file): bool => $this->ocrExtractionService->supportsFile($file))
            ->count();

        if ($ocrEligible === 0) {
            return 'no_ocr_eligible_files';
        }

        $hasAnyOcr = OcrResult::query()
            ->whereHas('ingestionFile', fn ($q) => $q->where('ingestion_batch_id', $batch->id))
            ->exists();

        $hasGoogleOcrRow = OcrResult::query()
            ->whereHas('ingestionFile', fn ($q) => $q->where('ingestion_batch_id', $batch->id))
            ->whereIn('engine_name', GoogleOcrStructuredDocumentCompiler::GOOGLE_ENGINES)
            ->exists();

        if ($hasAnyOcr && ! $hasGoogleOcrRow) {
            return 'legacy_ocr_only';
        }

        return 'no_usable_google_ocr';
    }

    /**
     * Queue OCR only for the given ingestion file IDs (must belong to the batch).
     * When {@code $clearOcrResultsFirst} is true, existing {@see OcrResult} rows for those files are removed before jobs run
     * so staff immediately see cleared state; each job still replaces rows on success.
     *
     * @param  list<int|string>  $ingestionFileIds
     * @return int Number of OCR jobs queued
     */
    public function executeForIngestionFileIds(
        IngestionBatch $batch,
        array $ingestionFileIds,
        bool $clearOcrResultsFirst = false,
        bool $queueAiExtractionAfterOcr = false,
    ): int {
        if ($ingestionFileIds === []) {
            return 0;
        }

        /** @var Collection<int, IngestionFile> $files */
        $files = IngestionFile::query()
            ->where('ingestion_batch_id', $batch->getKey())
            ->whereIn('id', $ingestionFileIds)
            ->orderBy('page_order')
            ->orderBy('id')
            ->get();

        $ocrFiles = $files->filter(fn (IngestionFile $file): bool => $this->ocrExtractionService->supportsFile($file));

        if ($ocrFiles->isEmpty()) {
            return 0;
        }

        $targetIds = $ocrFiles->pluck('id')->all();

        if ($clearOcrResultsFirst) {
            OcrResult::query()->whereIn('ingestion_file_id', $targetIds)->delete();
        }

        $jobs = $ocrFiles->map(fn (IngestionFile $file): RunOcrForFileJob => new RunOcrForFileJob($file))->all();

        $batchKey = $batch->getKey();

        $batch->forceFill(['status' => 'preprocessing'])->save();

        Bus::batch($jobs)
            ->name('ingestion-ocr-'.$batchKey)
            ->allowFailures()
            ->finally(function () use ($batchKey, $queueAiExtractionAfterOcr): void {
                IngestionBatch::query()->whereKey($batchKey)->update(['status' => 'ocr_done']);

                if (! $queueAiExtractionAfterOcr) {
                    return;
                }

                $fresh = IngestionBatch::query()->find($batchKey);

                if ($fresh === null || ! $fresh->hasOcrResults()) {
                    return;
                }

                $fresh->forceFill(['status' => 'ai_processing'])->save();
                RunBatchAiExtractionJob::dispatch($batchKey);
            })
            ->dispatch();

        return count($jobs);
    }
}
