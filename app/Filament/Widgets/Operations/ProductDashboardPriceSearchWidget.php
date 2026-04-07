<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\PriceHistory;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Operations\DashboardMappedProductBestPrices;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Dashboard quick lookup: search catalog products by name or SKU and see best recorded unit price (excl. VAT).
 */
final class ProductDashboardPriceSearchWidget extends TableWidget
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
        return ProductResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Quick product price lookup'))
            ->description(__('Search by product name or SKU. Shows the lowest ex-VAT unit price on record for each matching catalog product (same rules as price history).'))
            ->searchable()
            ->searchPlaceholder(__('Name or SKU…'))
            ->headerActions([
                Action::make('openPriceHistory')
                    ->label(__('Open price history'))
                    ->url(PriceHistory::getUrl())
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (): bool => PriceHistory::canAccess()),
            ])
            ->emptyStateHeading(__('Start typing'))
            ->emptyStateDescription(__('Enter part of the product name or SKU above to see matching prices.'))
            ->emptyStateIcon(Heroicon::OutlinedMagnifyingGlass)
            ->records(function (): Collection {
                $rows = app(DashboardMappedProductBestPrices::class)
                    ->searchByNameOrSku($this->getTableSearch(), 25);

                return $rows->mapWithKeys(function (object $row): array {
                    $id = (string) $row->product_id;

                    return [
                        $id => [
                            'id' => $id,
                            'product_id' => (int) $row->product_id,
                            'product_name' => $row->product_name,
                            'product_sku' => $row->product_sku,
                            'best_unit_price' => $row->best_unit_price,
                            'best_supplier_name' => $row->best_supplier_name,
                            'quote_date_label' => $row->quote_date_label,
                            'distinct_suppliers' => $row->distinct_suppliers,
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
                TextColumn::make('best_unit_price')
                    ->label(__('Best unit price (excl. VAT)'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('best_supplier_name')
                    ->label(__('Supplier on that quote'))
                    ->wrap(),
                TextColumn::make('quote_date_label')
                    ->label(__('Quote date'))
                    ->placeholder('—'),
                TextColumn::make('distinct_suppliers')
                    ->label(__('Suppliers in history'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => __(':count suppliers', ['count' => (int) $state]))
                    ->color(fn ($state): string => (int) $state > 1 ? 'success' : 'gray'),
            ])
            ->paginated(false);
    }
}
