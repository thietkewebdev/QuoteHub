<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\QuotationLinePresentation;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query->with([
                    'items.mappedProduct',
                ]);
            })
            ->searchPlaceholder(__('Search supplier, quote #, or line items (name, model, brand)'))
            ->searchable([
                function (Builder $query, string $search): void {
                    self::applyGlobalSearchConstraint($query, $search);
                },
            ])
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label(__('Status'))
                    ->getStateUsing(fn (Quotation $record): string => $record->approvalStatusLabel())
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->approvalStatusColor()),
                TextColumn::make('supplier_name')
                    ->label(__('Supplier name'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('line_product_labels')
                    ->label(__('Product name'))
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
                TextColumn::make('line_quantities')
                    ->label(__('Quantity'))
                    ->getStateUsing(function (Quotation $record): array {
                        if ($record->items->isEmpty()) {
                            return ['—'];
                        }

                        return $record->items
                            ->map(function (QuotationItem $item): string {
                                $q = QuotationLinePresentation::quantity($item->quantity);

                                return $q !== null && $q !== '' ? $q : '—';
                            })
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->alignment(Alignment::End)
                    ->placeholder('—'),
                TextColumn::make('line_amounts_incl_vat')
                    ->label(__('Amount (incl. VAT)'))
                    ->getStateUsing(function (Quotation $record): array {
                        if ($record->items->isEmpty()) {
                            return ['—'];
                        }

                        return $record->items
                            ->map(function (QuotationItem $item): string {
                                $incl = QuotationLinePresentation::lineTotalIncludingVat($item->line_total, $item->vat_percent);

                                return VietnamesePresentation::vnd($incl) ?? '—';
                            })
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->alignment(Alignment::End)
                    ->placeholder('—'),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('quote_date')
                    ->label(__('Quote date'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
            ])
            ->defaultSort('approved_at', 'desc')
            ->filters([
                SelectFilter::make('entry_source')
                    ->label(__('Entry source'))
                    ->options([
                        Quotation::ENTRY_SOURCE_AI_INGESTION => __('AI ingestion'),
                        Quotation::ENTRY_SOURCE_MANUAL => __('Manual entry'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === Quotation::ENTRY_SOURCE_AI_INGESTION) {
                            return $query->where(function (Builder $q): void {
                                $q->where('entry_source', Quotation::ENTRY_SOURCE_AI_INGESTION)
                                    ->orWhereNull('entry_source');
                            });
                        }

                        return $query->where('entry_source', Quotation::ENTRY_SOURCE_MANUAL);
                    }),
                SelectFilter::make('supplier_id')
                    ->label(__('Supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
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
                            ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('quote_date', '>=', $data['from']))
                            ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('quote_date', '<=', $data['until']));
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
                SelectFilter::make('approved_by')
                    ->label(__('Approved by'))
                    ->relationship('approver', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('approval')
                    ->label(__('Approval'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Approved'))
                    ->falseLabel(__('Pending'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('approved_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('approved_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->color('danger'),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->tooltip(__('Actions'))
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Global search: line items (raw_name, raw_model, brand) OR header fields (supplier, quote #) OR numeric id.
     */
    protected static function applyGlobalSearchConstraint(Builder $query, string $search): void
    {
        if (trim($search) === '') {
            return;
        }

        $connection = $query->getConnection();
        $term = generate_search_term_expression($search, isSearchForcedCaseInsensitive: null, databaseConnection: $connection);

        $query->where(function (Builder $outer) use ($term, $connection, $search): void {
            $outer->whereHas('items', function (Builder $itemsQuery) use ($term, $connection): void {
                $itemsQuery->where(function (Builder $inner) use ($term, $connection): void {
                    $isFirst = true;
                    foreach (['raw_name', 'raw_model', 'brand'] as $column) {
                        $clause = $isFirst ? 'where' : 'orWhere';
                        $inner->{$clause}(
                            generate_search_column_expression($column, isSearchForcedCaseInsensitive: null, databaseConnection: $connection),
                            'like',
                            "%{$term}%",
                        );
                        $isFirst = false;
                    }
                });
            });

            $outer->orWhere(function (Builder $q) use ($term, $connection): void {
                $q->where(
                    generate_search_column_expression('supplier_name', isSearchForcedCaseInsensitive: null, databaseConnection: $connection),
                    'like',
                    "%{$term}%",
                )->orWhere(
                    generate_search_column_expression('supplier_quote_number', isSearchForcedCaseInsensitive: null, databaseConnection: $connection),
                    'like',
                    "%{$term}%",
                );
            });

            if (ctype_digit($search)) {
                $outer->orWhere('id', '=', (int) $search);
            }
        });
    }
}
