<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reporting;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Services\Operations\ProcurementReportingQueries;
use App\Support\Locale\VietnamesePresentation;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class ApprovedQuotationsWithoutPurchaseOrderWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return QuotationResource::canViewAny() && PurchaseOrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Approved quotations without a purchase order'))
            ->description(__('Linked to a catalog supplier, has line items, and no PO recorded yet — candidates to place an order.'))
            ->emptyStateHeading(__('All caught up'))
            ->emptyStateDescription(__('No approved quotations in this state within the latest rows we scan.'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->records(function (): Collection {
                $quotes = app(ProcurementReportingQueries::class)->approvedQuotationsWithoutPurchaseOrder(50);

                return $quotes->mapWithKeys(fn (Quotation $q): array => [(string) $q->getKey() => $q]);
            })
            ->columns([
                TextColumn::make('id')
                    ->label(__('Quotation'))
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('view', ['record' => $record]))
                    ->formatStateUsing(fn ($state): string => '#'.$state)
                    ->color('primary'),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('supplier_quote_number')
                    ->label(__('Supplier quote #'))
                    ->placeholder('—'),
                TextColumn::make('approved_at')
                    ->label(__('Approved at'))
                    ->dateTime(VietnamesePresentation::DATETIME_FORMAT),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
