<?php

namespace App\Filament\Resources\Products\Tables;

use App\Services\Quotation\PriceHistoryQuery;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->with(['brand', 'category'])
                    ->addSelect([
                        'lowest_visible_unit_price' => PriceHistoryQuery::lowestVisibleUnitPricePerProductSubquery(),
                    ]);
            })
            ->columns([
                TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label(__('Product name'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('brand.name')
                    ->label(__('Brand'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('lowest_visible_unit_price')
                    ->label(__('Lowest unit price'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->placeholder('—')
                    ->alignment(Alignment::End)
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                        $sub = PriceHistoryQuery::lowestVisibleUnitPricePerProductSubquery();
                        $query->orderByRaw('('.$sub->toSql().') '.$dir, $sub->getBindings());
                    }),
                TextColumn::make('updated_at')
                    ->label(__('Last updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }
}
