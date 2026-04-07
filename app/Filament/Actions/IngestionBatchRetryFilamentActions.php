<?php

namespace App\Filament\Actions;

use App\Actions\Ingestion\RetryBatchAiExtractionAction;
use App\Actions\Ingestion\RetryBatchOcrAction;
use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Throwable;

/**
 * Filament table/header actions that delegate to ingestion retry domain actions.
 */
final class IngestionBatchRetryFilamentActions
{
    public static function retryOcrForBatch(): Action
    {
        return Action::make('retryOcrForBatch')
            ->label(__('Retry OCR (flagged files)'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('Retry OCR for flagged files?'))
            ->modalDescription(__('Existing OCR rows for image/PDF files that are missing text, empty, or low-confidence will be cleared, then OCR jobs will run only for those files. The batch moves to preprocessing, then ocr_done when the job batch finishes. Approved quotations are never modified. Ensure a queue worker is running.'))
            ->action(function (IngestionBatch $record): void {
                self::runOcrRetry($record);
            });
    }

    public static function retryAiForBatch(): Action
    {
        return Action::make('retryAiExtractionForBatch')
            ->label(__('Retry AI extraction'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('Retry AI extraction?'))
            ->modalDescription(__('Structured quotation data will be written again using the latest Google structured OCR (same updateOrCreate convention as a normal run). The batch moves to AI processing, then ai_done or ai_failed when the job finishes. Requires a running queue worker. Approved quotations are never modified.'))
            ->action(function (IngestionBatch $record): void {
                self::runAiRetry($record);
            });
    }

    public static function retryAiForExtractionRow(): Action
    {
        return Action::make('retryAiExtractionFromRow')
            ->label(__('Retry AI'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('Retry AI extraction for this batch?'))
            ->modalDescription(__('Same as batch retry: the AI extraction row for this batch is replaced when the job completes. Approved quotations are never modified.'))
            ->action(function (AiExtraction $record): void {
                $batch = $record->ingestionBatch;
                if ($batch === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('Batch not found'))
                        ->send();

                    return;
                }

                self::runAiRetry($batch);
            });
    }

    private static function runOcrRetry(IngestionBatch $record): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            Notification::make()
                ->danger()
                ->title(__('You must be signed in.'))
                ->send();

            return;
        }

        try {
            $count = app(RetryBatchOcrAction::class)->execute($record, $user);

            Notification::make()
                ->title(__('OCR retry queued'))
                ->body($count === 1
                    ? __('One OCR job was added for a flagged file.')
                    : __(':count OCR jobs were added for flagged files.', ['count' => $count]))
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title(__('OCR retry failed'))
                ->body($e->getMessage())
                ->send();
        }
    }

    private static function runAiRetry(IngestionBatch $record): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            Notification::make()
                ->danger()
                ->title(__('You must be signed in.'))
                ->send();

            return;
        }

        try {
            app(RetryBatchAiExtractionAction::class)->execute($record, $user);

            Notification::make()
                ->title(__('AI extraction queued'))
                ->body(__('The batch will move to AI done or AI failed when the job finishes.'))
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title(__('AI retry failed'))
                ->body($e->getMessage())
                ->send();
        }
    }
}
