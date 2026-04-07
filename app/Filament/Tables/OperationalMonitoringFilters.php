<?php

namespace App\Filament\Tables;

use App\Models\Supplier;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\QuotationLinePresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared read-only table filters for operational monitoring pages.
 */
final class OperationalMonitoringFilters
{
    /**
     * @return list<SelectFilter|Filter>
     */
    public static function ingestionBatchTable(string $tableAlias = 'ingestion_batches'): array
    {
        return [
            SelectFilter::make('supplier_id')
                ->label(__('Supplier'))
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload(),
            Filter::make('received_at')
                ->label(__('Received date'))
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
                ->query(function (Builder $query, array $data) use ($tableAlias): Builder {
                    return $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate("{$tableAlias}.received_at", '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate("{$tableAlias}.received_at", '<=', $data['until']));
                })
                ->indicateUsing(function (array $state): array {
                    $indicators = [];
                    if (filled($state['from'] ?? null)) {
                        $from = is_string($state['from']) ? $state['from'] : (string) $state['from'];
                        $indicators[] = Indicator::make(__('Received from').': '.(VietnamesePresentation::dateFromString($from) ?? $from));
                    }
                    if (filled($state['until'] ?? null)) {
                        $until = is_string($state['until']) ? $state['until'] : (string) $state['until'];
                        $indicators[] = Indicator::make(__('Received until').': '.(VietnamesePresentation::dateFromString($until) ?? $until));
                    }

                    return $indicators;
                }),
            SelectFilter::make('status')
                ->label(__('Status'))
                ->options(self::ingestionBatchStatusOptions())
                ->query(function (Builder $query, array $data) use ($tableAlias): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->where("{$tableAlias}.status", $data['value']);
                }),
        ];
    }

    /**
     * Filters for {@see QuotationItem} tables joined to quotations.
     *
     * @return list<SelectFilter|Filter>
     */
    public static function unmappedQuotationItemTable(): array
    {
        return [
            SelectFilter::make('supplier_id')
                ->label(__('Supplier'))
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('quotation', fn (Builder $q): Builder => $q->where('supplier_id', $data['value']));
                })
                ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            Filter::make('approved_at')
                ->label(__('Approval date'))
                ->schema([
                    DatePicker::make('from')
                        ->label(__('Approved from'))
                        ->native(false)
                        ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                    DatePicker::make('until')
                        ->label(__('Approved until'))
                        ->native(false)
                        ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereHas(
                            'quotation',
                            fn (Builder $q2): Builder => $q2->whereDate('approved_at', '>=', $data['from'])
                        ))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereHas(
                            'quotation',
                            fn (Builder $q2): Builder => $q2->whereDate('approved_at', '<=', $data['until'])
                        ));
                })
                ->indicateUsing(function (array $state): array {
                    $indicators = [];
                    if (filled($state['from'] ?? null)) {
                        $from = is_string($state['from']) ? $state['from'] : (string) $state['from'];
                        $indicators[] = Indicator::make(__('Approved from').': '.(VietnamesePresentation::dateFromString($from) ?? $from));
                    }
                    if (filled($state['until'] ?? null)) {
                        $until = is_string($state['until']) ? $state['until'] : (string) $state['until'];
                        $indicators[] = Indicator::make(__('Approved until').': '.(VietnamesePresentation::dateFromString($until) ?? $until));
                    }

                    return $indicators;
                }),
            SelectFilter::make('source_batch_status')
                ->label(__('Source batch status'))
                ->options(self::ingestionBatchStatusOptions())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas(
                        'quotation.ingestionBatch',
                        fn (Builder $b): Builder => $b->where('status', $data['value'])
                    );
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ingestionBatchStatusOptions(): array
    {
        return [
            'pending' => __('Pending'),
            'uploaded' => __('Uploaded'),
            'preprocessing' => __('Preprocessing'),
            'ocr_done' => __('OCR done'),
            'ai_processing' => __('AI processing'),
            'ai_done' => __('AI done'),
            'ai_failed' => __('AI failed'),
            'review_pending' => __('Review pending'),
            'review_rejected' => __('Review rejected'),
            'review_corrections_requested' => __('Corrections requested'),
            'approved' => __('Approved'),
        ];
    }

    public static function aiConfidenceBelowFilter(): Filter
    {
        return Filter::make('confidence_below')
            ->label(__('Confidence threshold'))
            ->schema([
                TextInput::make('max')
                    ->label(__('Show extractions with overall confidence below'))
                    ->numeric()
                    ->default(0.85)
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(1)
                    ->suffix('0–1'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $max = $data['max'] ?? null;
                if ($max === null || $max === '' || ! is_numeric($max)) {
                    $max = 0.85;
                }

                return $query->where('ai_extractions.confidence_overall', '<', (float) $max);
            })
            ->indicateUsing(function (array $state): array {
                $max = $state['max'] ?? null;
                if ($max === null || $max === '' || ! is_numeric($max)) {
                    return [];
                }

                $pct = QuotationLinePresentation::percent((float) $max * 100) ?? (string) $max;

                return [
                    Indicator::make(__('Confidence below').': '.$pct),
                ];
            });
    }

    /**
     * @return list<SelectFilter|Filter>
     */
    public static function aiExtractionMonitoringTable(): array
    {
        return [
            SelectFilter::make('supplier_id')
                ->label(__('Supplier'))
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas(
                        'ingestionBatch',
                        fn (Builder $q): Builder => $q->where('supplier_id', $data['value'])
                    );
                })
                ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            Filter::make('batch_received_at')
                ->label(__('Batch received'))
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
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereHas(
                            'ingestionBatch',
                            fn (Builder $b): Builder => $b->whereDate('received_at', '>=', $data['from'])
                        ))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereHas(
                            'ingestionBatch',
                            fn (Builder $b): Builder => $b->whereDate('received_at', '<=', $data['until'])
                        ));
                })
                ->indicateUsing(function (array $state): array {
                    $indicators = [];
                    if (filled($state['from'] ?? null)) {
                        $from = is_string($state['from']) ? $state['from'] : (string) $state['from'];
                        $indicators[] = Indicator::make(__('Received from').': '.(VietnamesePresentation::dateFromString($from) ?? $from));
                    }
                    if (filled($state['until'] ?? null)) {
                        $until = is_string($state['until']) ? $state['until'] : (string) $state['until'];
                        $indicators[] = Indicator::make(__('Received until').': '.(VietnamesePresentation::dateFromString($until) ?? $until));
                    }

                    return $indicators;
                }),
            SelectFilter::make('batch_status')
                ->label(__('Batch status'))
                ->options(self::ingestionBatchStatusOptions())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas(
                        'ingestionBatch',
                        fn (Builder $b): Builder => $b->where('status', $data['value'])
                    );
                }),
            self::aiConfidenceBelowFilter(),
        ];
    }
}
