<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Quotation;
use App\Models\QuotationItem;
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
                        'lowest_visible_unit_price' => QuotationItem::query()
                            ->selectRaw('MIN(quotation_items.unit_price)')
                            ->whereColumn('quotation_items.mapped_product_id', 'products.id')
                            ->whereHas('quotation', fn (Builder $q2): Builder => $q2->whereNotNull('approved_at'))
                            ->where(function (Builder $q2): void {
                                $q2->whereHas('quotation', fn (Builder $q3): Builder => $q3->where('entry_source', Quotation::ENTRY_SOURCE_MANUAL))
                                    ->orWhereHas('quotation', fn (Builder $q3): Builder => $q3->where('entry_source', Quotation::ENTRY_SOURCE_AI_INGESTION)
                                        ->whereHas('ingestionBatch'));
                            }),
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
                    ->sortable(),
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
