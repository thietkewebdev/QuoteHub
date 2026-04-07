<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reporting;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Services\Operations\ProcurementReportingQueries;
use App\Support\Locale\VietnamesePresentation;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class QuotationsValidityAlertsWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return QuotationResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Quotation validity — expired or next 14 days'))
            ->description(__('Approved quotations with a “valid until” date in the past or within the next two weeks.'))
            ->emptyStateHeading(__('No matching quotations'))
            ->emptyStateDescription(__('No approved quotations with validity ending in this window.'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->records(function (): Collection {
                $quotes = app(ProcurementReportingQueries::class)->quotationsValidityWindow(14, 50);

                return $quotes->mapWithKeys(fn (Quotation $q): array => [(string) $q->getKey() => $q]);
            })
            ->columns([
                TextColumn::make('id')
                    ->label(__('Quotation'))
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('view', ['record' => $record]))
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->color('primary'),
                TextColumn::make('supplier_name')
                    ->label(__('Supplier'))
                    ->wrap(),
                TextColumn::make('valid_until')
                    ->label(__('Valid until'))
                    ->formatStateUsing(function ($state): string {
                        $d = $state instanceof Carbon ? $state : Carbon::parse((string) $state);
                        $label = $d->format(VietnamesePresentation::DATE_FORMAT);
                        if ($d->isPast() && ! $d->isToday()) {
                            return $label.' ('.__('expired').')';
                        }

                        return $label;
                    }),
                TextColumn::make('lifecycle')
                    ->label(__('Status'))
                    ->badge()
                    ->getStateUsing(fn (Quotation $record): string => $record->approvalStatusLabel())
                    ->color(fn (Quotation $record): string => $record->approvalStatusColor()),
            ])
            ->paginated(false);
    }
}
