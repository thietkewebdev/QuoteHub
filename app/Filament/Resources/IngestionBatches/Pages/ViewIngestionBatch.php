<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Actions\Ingestion\DispatchBatchOcrJobsAction;
use App\Filament\Actions\IngestionBatchRetryFilamentActions;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Jobs\AI\RunBatchAiExtractionJob;
use App\Models\IngestionBatch;
use App\Services\Operations\IngestionBatchRetryPolicy;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIngestionBatch extends ViewRecord
{
    protected static string $resource = IngestionBatchResource::class;

    protected string $view = 'filament.resources.ingestion-batches.pages.view-ingestion-batch';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'aiExtraction.supplierExtractionProfile.supplier',
            'aiExtraction',
            'supplier',
            'quotation',
        ]);
    }

    /**
     * Livewire poll interval (e.g. "3s") while batch status may change from background jobs; null when idle.
     */
    public function getBatchStatusPollInterval(): ?string
    {
        if (! $this->shouldPollIngestionBatchStatus()) {
            return null;
        }

        $seconds = (int) config('ingestion.view_batch_status_poll_seconds', 3);

        return max(2, min(60, $seconds)).'s';
    }

    public function refreshIngestionBatchStatus(): void
    {
        if (! $this->record instanceof IngestionBatch) {
            return;
        }

        $key = $this->record->getKey();

        $this->record = IngestionBatch::query()
            ->whereKey($key)
            ->with([
                'aiExtraction.supplierExtractionProfile.supplier',
                'aiExtraction',
                'supplier',
                'quotation',
            ])
            ->firstOrFail();
    }

    protected function shouldPollIngestionBatchStatus(): bool
    {
        if (! $this->record instanceof IngestionBatch) {
            return false;
        }

        return in_array($this->record->status, ['preprocessing', 'ai_processing'], true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOcrCapture')
                ->label(__('Raw OCR'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->url(fn (IngestionBatch $record): string => IngestionBatchResource::getUrl('ocrCapture', ['record' => $record]))
                ->visible(function (IngestionBatch $record): bool {
                    $record->loadMissing(['quotationReviewDraft']);
                    $p = $record->quotationReviewDraft?->payload_json;

                    return is_array($p)
                        && (isset($p['extraction_status']) || filled($p['raw_full_text'] ?? null));
                }),
            Action::make('runOcrAndAi')
                ->label(__('Run OCR & AI'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->visible(fn (IngestionBatch $record): bool => IngestionBatchResource::canEdit($record))
                ->disabled(fn (IngestionBatch $record): bool => $record->file_count === 0
                    || $record->status === 'preprocessing'
                    || $record->status === 'ai_processing')
                ->requiresConfirmation()
                ->modalHeading(__('Run OCR then AI extraction?'))
                ->modalDescription(__('OCR jobs run first for PDFs and images using Google Document AI / Vision only. When they finish, AI extraction starts automatically if structured OCR content is available. Requires a queue worker.'))
                ->action(function (IngestionBatch $record): void {
                    $result = app(DispatchBatchOcrJobsAction::class)->executeThenQueueAi($record);

                    if ($result['ocr_jobs'] > 0) {
                        Notification::make()
                            ->title(__('OCR queued; AI will follow'))
                            ->body($result['ocr_jobs'] === 1
                                ? __('One OCR job was queued. AI extraction will start automatically when OCR completes.')
                                : __(':count OCR jobs were queued. AI extraction will start automatically when OCR completes.', ['count' => $result['ocr_jobs']]))
                            ->success()
                            ->send();

                        return;
                    }

                    if ($result['ai_queued'] && $result['ai_immediate']) {
                        Notification::make()
                            ->title(__('AI extraction queued'))
                            ->body(__('There was nothing new to OCR; AI extraction was queued using existing Google OCR results.'))
                            ->success()
                            ->send();

                        return;
                    }

                    $reason = $result['idle_reason'] ?? 'no_usable_google_ocr';
                    $body = match ($reason) {
                        'no_files' => __('This batch has no files attached. Upload at least one file, then try again.'),
                        'no_ocr_eligible_files' => __('This batch has no PDF or image files. Google OCR only runs on PDF, JPEG, PNG, GIF, and WebP. Add a scan or export, or use files the pipeline can read without OCR.'),
                        'legacy_ocr_only' => __('Existing OCR results are not from Google Document AI or Vision. Re-run OCR so AI extraction can use structured Google output.'),
                        default => __('No new OCR jobs were queued and no usable Google structured OCR was found. Check failed jobs or logs, confirm GCP credentials and Document AI / Vision configuration, and ensure a queue worker is running.'),
                    };

                    Notification::make()
                        ->title(__('Nothing to process'))
                        ->body($body)
                        ->warning()
                        ->send();
                }),
            Action::make('runOcr')
                ->label(__('Run OCR only'))
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('gray')
                ->disabled(fn (IngestionBatch $record): bool => $record->file_count === 0 || $record->status === 'preprocessing')
                ->requiresConfirmation()
                ->modalHeading(__('Run OCR on all files?'))
                ->modalDescription(__('Queued jobs run Google OCR (Document AI for PDFs, Vision for images). The batch moves to preprocessing, then ocr_done when the job batch finishes. Ensure a queue worker is running.'))
                ->action(function (IngestionBatch $record): void {
                    $count = app(DispatchBatchOcrJobsAction::class)->execute($record);

                    Notification::make()
                        ->title(__('OCR queued'))
                        ->body($count === 1
                            ? __('One OCR job was added to the queue.')
                            : __(':count OCR jobs were added to the queue.', ['count' => $count]))
                        ->success()
                        ->send();
                }),
            IngestionBatchRetryFilamentActions::retryOcrForBatch()
                ->visible(fn (IngestionBatch $record): bool => IngestionBatchResource::canEdit($record)
                    && IngestionBatchRetryPolicy::canQueueOcrRetry($record)),
            Action::make('runAiExtraction')
                ->label(__('Run AI Extraction'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (IngestionBatch $record): bool => $record->status === 'ocr_done'
                    && $record->hasOcrResults()
                    && IngestionBatchResource::canEdit($record))
                ->disabled(fn (IngestionBatch $record): bool => ! $record->hasOcrResults())
                ->requiresConfirmation()
                ->modalHeading(__('Run AI extraction on Google OCR output?'))
                ->modalDescription(__('Structured quotation data will be written to this batch\'s AI extraction record. Requires usable Google structured OCR on at least one file. A queue worker must be running.'))
                ->action(function (IngestionBatch $record): void {
                    $record->forceFill(['status' => 'ai_processing'])->save();
                    RunBatchAiExtractionJob::dispatch($record->id);

                    Notification::make()
                        ->title(__('AI extraction queued'))
                        ->body(__('The batch will move to AI done or AI failed when the job finishes.'))
                        ->success()
                        ->send();
                }),
            IngestionBatchRetryFilamentActions::retryAiForBatch()
                ->visible(fn (IngestionBatch $record): bool => IngestionBatchResource::canEdit($record)
                    && IngestionBatchRetryPolicy::canQueueAiRetry($record)),
            Action::make('reviewQuotation')
                ->label(__('Review & approve'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->url(fn (IngestionBatch $record): string => IngestionBatchResource::getUrl('reviewQuotation', ['record' => $record]))
                ->visible(function (IngestionBatch $record): bool {
                    if ($record->quotation !== null) {
                        return false;
                    }

                    if ($record->aiExtraction === null) {
                        return false;
                    }

                    return in_array($record->status, [
                        'ai_done',
                        'review_pending',
                        'review_rejected',
                        'review_corrections_requested',
                    ], true);
                }),
            EditAction::make(),
        ];
    }
}
