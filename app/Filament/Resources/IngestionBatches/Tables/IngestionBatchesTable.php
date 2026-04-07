<?php

namespace App\Filament\Resources\IngestionBatches\Tables;

use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Models\IngestionBatch;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IngestionBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('source_channel')
                    ->label(__('Channel'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('received_at')
                    ->label(__('Received'))
                    ->dateTime(VietnamesePresentation::DATETIME_FORMAT)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('file_count')
                    ->label(__('Files'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label(__('Uploaded by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('reviewQuotation')
                    ->label(__('Review'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (IngestionBatch $record): string => IngestionBatchResource::getUrl('reviewQuotation', ['record' => $record]))
                    ->visible(fn (IngestionBatch $record): bool => $record->quotation()->doesntExist()
                        && $record->aiExtraction()->exists()
                        && in_array($record->status, [
                            'ai_done',
                            'review_pending',
                            'review_rejected',
                            'review_corrections_requested',
                        ], true)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
