<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\PurchaseOrder;
use App\Support\Locale\VietnamesePresentation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Purchase order'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->schema([
                        TextEntry::make('po_number')
                            ->label(__('PO number'))
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('supplier.name')
                            ->label(__('Supplier'))
                            ->placeholder('—'),
                        TextEntry::make('quotation_id')
                            ->label(__('Source quotation'))
                            ->placeholder('—')
                            ->url(fn (PurchaseOrder $record): ?string => $record->quotation_id
                                ? QuotationResource::getUrl('view', ['record' => $record->quotation_id])
                                : null)
                            ->formatStateUsing(fn (?int $state): string => $state ? '#'.$state : '—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => PurchaseOrder::statusOptions()[$state] ?? (string) $state)
                            ->color(fn (?string $state): string => match ($state) {
                                PurchaseOrder::STATUS_ISSUED => 'success',
                                PurchaseOrder::STATUS_CANCELLED => 'danger',
                                PurchaseOrder::STATUS_COMPLETED => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('order_date')
                            ->label(__('Order date'))
                            ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                        TextEntry::make('expected_delivery_date')
                            ->label(__('Expected delivery'))
                            ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT))
                            ->placeholder('—'),
                        TextEntry::make('currency')
                            ->label(__('Currency')),
                        TextEntry::make('subtotal_before_tax')
                            ->label(__('Subtotal (before tax)'))
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('tax_amount')
                            ->label(__('Tax amount'))
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('total_amount')
                            ->label(__('Total'))
                            ->alignEnd()
                            ->weight(FontWeight::SemiBold)
                            ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('creator.name')
                            ->label(__('Created by'))
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
}
