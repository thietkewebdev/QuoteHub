<?php

namespace App\Filament\Tables;

use App\Filament\Actions\MapQuotationItemToProductAction;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\PriceHistoryGroupKeySql;
use App\Support\Quotation\QuotationLinePresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PriceHistoryTable
{
    public static function configure(Table $table): Table
    {
        $comparisonGroup = Group::make('price_history_group_key')
            ->label(__('Comparison group'))
            ->collapsible()
            ->titlePrefixedWithLabel(false)
            ->getTitleFromRecordUsing(function (Model $record): string {
                /** @var QuotationItem $record */
                $key = (string) ($record->getAttribute('price_history_group_key') ?? '');

                if ($key === '') {
                    return __('Unknown group');
                }

                if (str_starts_with($key, 'p:')) {
                    $id = (int) substr($key, 2);
                    $mp = $record->mappedProduct;
                    $label = $mp?->name ?: ('#'.$id);
                    if (filled($mp?->sku)) {
                        $label .= ' ('.$mp->sku.')';
                    }

                    return __('Product: :label', ['label' => $label]);
                }

                if (str_starts_with($key, 'm:')) {
                    return __('Model: :value', ['value' => substr($key, 2) ?: '—']);
                }

                if (str_starts_with($key, 'n:')) {
                    $rest = substr($key, 2);
                    [$name, $brand] = array_pad(explode('|', $rest, 2), 2, '');

                    return __('Product: :name · Brand: :brand', [
                        'name' => $name !== '' ? $name : '—',
                        'brand' => $brand !== '' ? $brand : '—',
                    ]);
                }

                return $key;
            });

        return $table
            ->queryStringIdentifier('priceHistory')
            ->striped()
            ->recordUrl(fn (QuotationItem $record): string => QuotationResource::getUrl('view', ['record' => $record->quotation_id]))
            ->description(__('Click a row to open the quotation. Filter by supplier, dates, or line brand.'))
            ->columns([
                TextColumn::make('quotation.supplier_name')
                    ->label(__('Supplier'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->iconColor('gray')
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('quotation.quote_date')
                    ->label(__('Quote date'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray')
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $query->orderBy('quotations.quote_date', $direction)
                            ->orderBy('quotation_items.id', $direction);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('raw_name')
                    ->label(__('Quoted line'))
                    ->icon(Heroicon::OutlinedCube)
                    ->iconColor('gray')
                    ->wrap()
                    ->searchable(['raw_name', 'raw_model', 'brand'])
                    ->description(fn (QuotationItem $record): ?string => self::quotedLineSecondary($record)),
                TextColumn::make('quantity')
                    ->label(__('Qty'))
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::quantity($state)),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold)
                    ->sortable(['quotation_items.unit_price', 'quotation_items.id'])
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('vat_percent')
                    ->label(__('VAT %'))
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::percent($state)),
                TextColumn::make('total_incl_vat')
                    ->label(__('Line total'))
                    ->alignEnd()
                    ->weight(FontWeight::Medium)
                    ->state(fn (QuotationItem $record): ?float => QuotationLinePresentation::lineTotalIncludingVat(
                        $record->line_total,
                        $record->vat_percent,
                    ))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
            ])
            ->defaultSort('quotation.quote_date', 'desc')
            ->groups([$comparisonGroup])
            ->defaultGroup('price_history_group_key')
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
                SelectFilter::make('line_brand')
                    ->label(__('Brand'))
                    ->searchable()
                    ->options(fn (): array => QuotationItem::query()
                        ->whereHas('quotation')
                        ->whereNotNull('brand')
                        ->where('brand', '!=', '')
                        ->distinct()
                        ->orderBy('brand')
                        ->pluck('brand', 'brand')
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('quotation_items.brand', $data['value']);
                    }),
                TernaryFilter::make('has_raw_model')
                    ->label(__('Model # on line'))
                    ->placeholder(__('All lines'))
                    ->trueLabel(__('With model'))
                    ->falseLabel(__('Without model'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereRaw(PriceHistoryGroupKeySql::hasNonEmptyRawModelPredicate('quotation_items')),
                        false: fn (Builder $query): Builder => $query->whereRaw('NOT ('.PriceHistoryGroupKeySql::hasNonEmptyRawModelPredicate('quotation_items').')'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                MapQuotationItemToProductAction::make(),
            ])
            ->paginated([25, 50, 100]);
    }

    private static function quotedLineSecondary(QuotationItem $record): ?string
    {
        $parts = array_values(array_filter([
            filled($record->raw_model) ? trim((string) $record->raw_model) : null,
            filled($record->brand) ? trim((string) $record->brand) : null,
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
