<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Actions\Ingestion\DispatchBatchOcrJobsAction;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Models\IngestionBatch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIngestionBatch extends ViewRecord
{
    protected static string $resource = IngestionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queueOcrStub')
                ->label(__('Queue OCR (stub)'))
                ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                ->color('gray')
                ->disabled(fn (IngestionBatch $record): bool => $record->file_count === 0)
                ->requiresConfirmation()
                ->modalHeading(__('Queue OCR jobs?'))
                ->modalDescription(__('Dispatches one placeholder job per file. No real OCR runs in this milestone.'))
                ->action(function (IngestionBatch $record): void {
                    $count = app(DispatchBatchOcrJobsAction::class)->execute($record);

                    Notification::make()
                        ->title(__('OCR jobs queued'))
                        ->body($count === 1
                            ? __('One stub job was dispatched.')
                            : __(':count stub jobs were dispatched.', ['count' => $count]))
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
