<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reporting;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\Operations\ProcurementReportingQueries;
use App\Support\Locale\VietnamesePresentation;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class PurchaseOrdersDueSoonWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PurchaseOrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('PO delivery — overdue or next 14 days'))
            ->description(__('Draft or issued POs with an expected delivery date in the past or within the next two weeks.'))
            ->emptyStateHeading(__('Nothing in this window'))
            ->emptyStateDescription(__('No draft/issued POs with expected dates overdue or within 14 days.'))
            ->emptyStateIcon(Heroicon::OutlinedTruck)
            ->records(function (): Collection {
                $orders = app(ProcurementReportingQueries::class)->purchaseOrdersDueOrOverdue(14, 50);

                return $orders->mapWithKeys(fn (PurchaseOrder $order): array => [(string) $order->getKey() => $order]);
            })
            ->columns([
                TextColumn::make('po_number')
                    ->label(__('PO number'))
                    ->url(fn (PurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PurchaseOrder::statusOptions()[$state] ?? (string) $state),
                TextColumn::make('expected_delivery_date')
                    ->label(__('Expected delivery'))
                    ->formatStateUsing(function ($state): string {
                        if ($state === null) {
                            return '—';
                        }

                        $d = $state instanceof Carbon ? $state : Carbon::parse((string) $state);
                        $label = $d->format(VietnamesePresentation::DATE_FORMAT);
                        if ($d->isPast() && ! $d->isToday()) {
                            return $label.' ('.__('overdue').')';
                        }

                        return $label;
                    }),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
