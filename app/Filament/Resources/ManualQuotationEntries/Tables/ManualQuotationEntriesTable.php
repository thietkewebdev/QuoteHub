<?php

namespace App\Filament\Resources\ManualQuotationEntries\Tables;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\QuotationReviewDraft;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManualQuotationEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('payload_json.supplier_name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('review_status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('approved_quotation_id')
                    ->label(__('Quotation'))
                    ->placeholder('—')
                    ->url(fn (QuotationReviewDraft $record): ?string => $record->approved_quotation_id
                        ? QuotationResource::getUrl('view', ['record' => $record->approved_quotation_id])
                        : null),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (QuotationReviewDraft $record): bool => $record->approved_quotation_id === null),
                Action::make('viewQuotation')
                    ->label(__('View quotation'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (QuotationReviewDraft $record): bool => $record->approved_quotation_id !== null)
                    ->url(fn (QuotationReviewDraft $record): string => QuotationResource::getUrl('view', ['record' => $record->approved_quotation_id])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
