<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Reporting;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrderLine;
use App\Services\Operations\ProcurementReportingQueries;
use App\Support\Locale\VietnamesePresentation;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Latest recorded purchase (PO line) per catalog product + supplier, searchable by product name or SKU.
 */
final class ProductLastPurchaseFromPoWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function updatedTableSearch(): void
    {
        $this->flushCachedTableRecords();

        if ($this->getTable()->persistsSearchInSession()) {
            session()->put(
                $this->getTableSearchSessionKey(),
                $this->tableSearch,
            );
        }

        if ($this->getTable()->shouldDeselectAllRecordsWhenFiltered()) {
            $this->deselectAllTableRecords();
        }

        $this->resetPage();
    }

    public static function canView(): bool
    {
        return ProductResource::canViewAny() && PurchaseOrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Last purchase from PO (by product & supplier)'))
            ->description(__('For each product–supplier pair, shows the most recent PO line unit price and order date. Search by product name or SKU.'))
            ->searchable()
            ->searchPlaceholder(__('Product name or SKU…'))
            ->emptyStateHeading(__('No purchase lines yet'))
            ->emptyStateDescription(__('Create POs with catalog products on lines to build this report, or adjust your search.'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag)
            ->records(function (): Collection {
                $rows = app(ProcurementReportingQueries::class)->latestPurchaseLineRowsByProductAndSupplier(
                    $this->getTableSearch(),
                    35,
                );

                return collect($rows)->mapWithKeys(function (array $row): array {
                    /** @var PurchaseOrderLine $line */
                    $line = $row['line'];

                    return [
                        $row['key'] => [
                            'key' => $row['key'],
                            'product_id' => $line->product_id,
                            'product_name' => $line->product?->name ?? '—',
                            'product_sku' => $line->product?->sku,
                            'supplier_name' => $line->purchaseOrder?->supplier?->name ?? '—',
                            'order_date' => $line->purchaseOrder?->order_date,
                            'unit_price' => $line->unit_price,
                            'po_number' => $line->purchaseOrder?->po_number,
                            'purchase_order_id' => $line->purchase_order_id,
                        ],
                    ];
                });
            })
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('Product'))
                    ->wrap()
                    ->url(fn (array $record): string => ProductResource::getUrl('view', ['record' => $record['product_id']])),
                TextColumn::make('product_sku')
                    ->label(__('SKU'))
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('supplier_name')
                    ->label(__('Supplier'))
                    ->wrap(),
                TextColumn::make('order_date')
                    ->label(__('Order date'))
                    ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT)),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('po_number')
                    ->label(__('PO'))
                    ->url(fn (array $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record['purchase_order_id']]))
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
