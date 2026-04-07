<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
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

        $batch = $batch->refresh();

        if ((bool) config('ingestion.google_ocr.enabled', true)) {
            app(IngestionGoogleOcrDraftService::class)->captureForBatch($batch->loadMissing(['files']));
        }

        return $batch;
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
    }
}
