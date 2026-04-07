<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Actions\Ingestion\DispatchBatchOcrJobsAction;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Jobs\Ingestion\CaptureGoogleOcrDraftForBatchJob;
use App\Models\IngestionBatch;
use App\Services\Ingestion\IngestionBatchCreationService;
use App\Services\Ingestion\IngestionFileStorageService;
use App\Services\Ingestion\IngestionGoogleOcrDraftService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateIngestionBatch extends CreateRecord
{
    protected static string $resource = IngestionBatchResource::class;

    protected int $skippedDuplicateUploads = 0;

    protected function handleRecordCreation(array $data): Model
    {
        $paths = $data['uploads'] ?? [];
        $originalNames = $data['upload_original_names'] ?? [];
        unset($data['uploads'], $data['upload_original_names']);

        $batchPayload = [
            'source_channel' => $data['source_channel'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'received_at' => $data['received_at'],
            'notes' => $data['notes'] ?? null,
        ];

        $batch = app(IngestionBatchCreationService::class)->createPendingBatch(
            $batchPayload,
            auth()->id(),
        );

        $fileService = app(IngestionFileStorageService::class);

        $pathList = match (true) {
            is_array($paths) => array_values($paths),
            filled($paths) => [$paths],
            default => [],
        };

        $nameList = match (true) {
            is_array($originalNames) => array_values($originalNames),
            filled($originalNames) => [$originalNames],
            default => null,
        };

        $result = $fileService->persistStagedUploads($batch, $pathList, $nameList);

        $this->skippedDuplicateUploads = $result['skipped_duplicates'];

        DB::afterCommit(fn () => $fileService->deleteStagedRelativePaths($pathList));

        return $batch->refresh();
    }

    protected function afterCreate(): void
    {
        if ($this->skippedDuplicateUploads > 0) {
            Notification::make()
                ->warning()
                ->title(__('Duplicate files skipped'))
                ->body(__('Skipped :count duplicate file(s) in this upload (same SHA-256 as another selected file).', ['count' => $this->skippedDuplicateUploads]))
                ->send();
        }

        $this->scheduleGoogleOcrDraftCaptureAfterCommit();
        $this->scheduleAutoDispatchOcrAfterCreate();
    }

    /**
     * Google OCR for the review draft runs after DB commit. On async queues it is deferred to a job so the
     * Livewire "create" request returns quickly (Document AI on PDF can exceed HTTP timeouts).
     */
    protected function scheduleGoogleOcrDraftCaptureAfterCommit(): void
    {
        if (! (bool) config('ingestion.google_ocr.enabled', true)) {
            return;
        }

        $record = $this->getRecord();
        if (! $record instanceof IngestionBatch || $record->file_count === 0) {
            return;
        }

        $batchId = (int) $record->getKey();

        DB::afterCommit(function () use ($batchId): void {
            if (in_array(config('queue.default'), ['sync', 'null'], true)) {
                $batch = IngestionBatch::query()->with('files')->find($batchId);
                if ($batch !== null) {
                    app(IngestionGoogleOcrDraftService::class)->captureForBatch($batch);
                }

                return;
            }

            CaptureGoogleOcrDraftForBatchJob::dispatch($batchId);
        });
    }

    /**
     * Queue OCR (+ AI when applicable) after the Filament create transaction commits.
     * Avoids sync/null drivers so the HTTP request does not run OCR inline.
     */
    protected function scheduleAutoDispatchOcrAfterCreate(): void
    {
        if (! (bool) config('ingestion.auto_dispatch_ocr_after_create', true)) {
            return;
        }

        $record = $this->getRecord();
        if (! $record instanceof IngestionBatch || $record->file_count === 0) {
            return;
        }

        if (in_array(config('queue.default'), ['sync', 'null'], true)) {
            return;
        }

        $batchId = (int) $record->getKey();

        DB::afterCommit(function () use ($batchId): void {
            $batch = IngestionBatch::query()->find($batchId);
            if ($batch === null || $batch->file_count === 0) {
                return;
            }

            app(DispatchBatchOcrJobsAction::class)->executeThenQueueAi($batch);
        });
    }
}
