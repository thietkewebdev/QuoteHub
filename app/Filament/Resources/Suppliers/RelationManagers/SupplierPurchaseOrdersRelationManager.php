<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\PurchaseOrder;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Purchase orders');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['quotation']))
            ->columns([
                TextColumn::make('po_number')
                    ->label(__('PO number'))
                    ->url(fn (PurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record]))
                    ->color('primary')
                    ->searchable(),
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
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('quotation_id')
                    ->label(__('Quotation'))
                    ->placeholder('—')
                    ->url(fn (PurchaseOrder $record): ?string => $record->quotation_id
                        ? QuotationResource::getUrl('view', ['record' => $record->quotation_id])
                        : null)
                    ->formatStateUsing(fn (PurchaseOrder $record): string => $record->quotation_id ? '#'.$record->quotation_id : '—'),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (PurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25, 50]);
    }
}
