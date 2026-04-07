<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierQuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Recent quotations');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['items.mappedProduct'])
                ->orderByDesc('approved_at')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable()
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('quote_date')
                    ->label(__('Quote date'))
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('line_product_labels')
                    ->label(__('Product'))
                    ->getStateUsing(function (Quotation $record): array {
                        if ($record->items->isEmpty()) {
                            return ['—'];
                        }

                        return $record->items
                            ->map(fn (QuotationItem $item): string => $item->displayLabel())
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('line_unit_prices')
                    ->label(__('Unit price'))
                    ->getStateUsing(function (Quotation $record): array {
                        if ($record->items->isEmpty()) {
                            return ['—'];
                        }

                        return $record->items
                            ->map(fn (QuotationItem $item): string => VietnamesePresentation::vnd($item->unit_price) ?? '—')
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->alignment(Alignment::End)
                    ->placeholder('—'),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('approval_status')
                    ->label(__('Status'))
                    ->getStateUsing(fn (Quotation $record): string => $record->approvalStatusLabel())
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->approvalStatusColor()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
