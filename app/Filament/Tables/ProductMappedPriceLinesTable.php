<?php

namespace App\Filament\Tables;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\QuotationLinePresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProductMappedPriceLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (QuotationItem $record): string => QuotationResource::getUrl('view', ['record' => $record->quotation_id]))
            ->description(__('Prices from approved lines mapped to this product. Click a row to open the full quotation if needed.'))
            ->columns([
                TextColumn::make('quotation.supplier_name')
                    ->label(__('Supplier name'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('quotation.quote_date')
                    ->label(__('Quote date'))
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $query->orderBy('quotations.quote_date', $direction)
                            ->orderBy('quotation_items.id', $direction);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::quantity($state)),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->sortable(['quotation_items.unit_price', 'quotation_items.id'])
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('vat_percent')
                    ->label(__('VAT %'))
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::percent($state)),
                TextColumn::make('total_incl_vat')
                    ->label(__('Total'))
                    ->state(fn (QuotationItem $record): ?float => QuotationLinePresentation::lineTotalIncludingVat(
                        $record->line_total,
                        $record->vat_percent,
                    ))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
            ])
            ->defaultSort('quotation.quote_date', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label(__('Supplier'))
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('quotations.supplier_id', $data['value']);
                    }),
                Filter::make('quote_date')
                    ->label(__('Quote date'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('From'))
                            ->native(false)
                            ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                        DatePicker::make('until')
                            ->label(__('Until'))
                            ->native(false)
                            ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('quotations.quote_date', '>=', $data['from']))
                            ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('quotations.quote_date', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $state): array {
                        $indicators = [];
                        if (filled($state['from'] ?? null)) {
                            $from = is_string($state['from']) ? $state['from'] : (string) $state['from'];
                            $indicators[] = Indicator::make(__('Quote date from').': '.(VietnamesePresentation::dateFromString($from) ?? $from));
                        }
                        if (filled($state['until'] ?? null)) {
                            $until = is_string($state['until']) ? $state['until'] : (string) $state['until'];
                            $indicators[] = Indicator::make(__('Quote date until').': '.(VietnamesePresentation::dateFromString($until) ?? $until));
                        }

                        return $indicators;
                    }),
            ])
            ->paginated([25, 50, 100]);
    }
}
