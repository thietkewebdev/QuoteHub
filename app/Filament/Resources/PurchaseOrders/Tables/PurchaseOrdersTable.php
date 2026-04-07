<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\PurchaseOrder;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label(__('PO number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('quotation_id')
                    ->label(__('Quotation'))
                    ->sortable()
                    ->placeholder('—')
                    ->url(fn (PurchaseOrder $record): ?string => $record->quotation_id
                        ? QuotationResource::getUrl('view', ['record' => $record->quotation_id])
                        : null)
                    ->formatStateUsing(fn (?int $state): string => $state ? '#'.$state : '—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PurchaseOrder::statusOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        PurchaseOrder::STATUS_ISSUED => 'success',
                        PurchaseOrder::STATUS_CANCELLED => 'danger',
                        PurchaseOrder::STATUS_COMPLETED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('order_date')
                    ->label(__('Order date'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PurchaseOrder::statusOptions()),
                SelectFilter::make('supplier_id')
                    ->label(__('Supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
