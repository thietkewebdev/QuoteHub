<?php

namespace App\Filament\Resources\Quotations\RelationManagers;

use App\Filament\Actions\MapQuotationItemToProductAction;
use App\Models\QuotationItem;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\QuotationLinePresentation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QuotationItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Line items');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('mappedProduct'))
            ->recordTitleAttribute('raw_name')
            ->columns([
                TextColumn::make('raw_name')
                    ->label(__('Product name'))
                    ->wrap(),
                TextColumn::make('raw_model')
                    ->label(__('Model'))
                    ->wrap()
                    ->placeholder('—'),
                // DB column `quotation_items.brand` (review/AI field). Not auto-derived from product name in this table.
                TextColumn::make('brand')
                    ->label(__('Brand'))
                    ->placeholder('—'),
                TextColumn::make('mappedProduct.name')
                    ->label(__('Mapped product'))
                    ->wrap()
                    ->placeholder('—')
                    ->description(fn (QuotationItem $record): ?string => $record->mappedProduct?->sku
                        ? __('SKU: :sku', ['sku' => $record->mappedProduct->sku])
                        : null),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::quantity($state)),
                TextColumn::make('vat_percent')
                    ->label(__('VAT %'))
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::percent($state)),
                TextColumn::make('line_total_incl_vat')
                    ->label(__('Amount (incl. VAT)'))
                    ->state(fn (QuotationItem $record): ?float => QuotationLinePresentation::lineTotalIncludingVat($record->line_total, $record->vat_percent))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $expr = '(CASE WHEN quotation_items.vat_percent IS NULL THEN quotation_items.line_total ELSE quotation_items.line_total * (1 + quotation_items.vat_percent / 100.0) END)';
                        $query->orderByRaw($expr.' '.$direction)
                            ->orderBy('quotation_items.line_no', $direction);
                    }),
                TextColumn::make('specs_text')
                    ->label(__('Specs'))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->recordActions([
                MapQuotationItemToProductAction::make(),
            ])
            ->defaultSort('line_no')
            ->paginated([25, 50, 100]);
    }
}
