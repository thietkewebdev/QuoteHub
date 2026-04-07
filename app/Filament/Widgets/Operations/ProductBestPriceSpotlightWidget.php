<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Resources\Products\ProductResource;
use App\Services\Operations\DashboardMappedProductBestPrices;
use App\Support\Locale\VietnamesePresentation;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class ProductBestPriceSpotlightWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Best unit price spotlight'))
            ->description(__('Lowest ex-VAT unit price per product among visible approved lines, with the supplier on that quote. Sorted by most recent quote date.'))
            ->records(fn (): Collection => app(DashboardMappedProductBestPrices::class)
                ->recentSpotlight(14)
                ->mapWithKeys(function (object $row): array {
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
                }))
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
