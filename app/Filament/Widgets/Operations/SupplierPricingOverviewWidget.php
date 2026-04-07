<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\PriceHistory;
use App\Services\Operations\DashboardSupplierOverview;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class SupplierPricingOverviewWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Supplier overview'))
            ->description(__('Catalog-mapped lines only. “Best price” = lowest unit price (excl. VAT) on that product among visible approved history. Compared only when at least two suppliers quoted the same catalog product.'))
            ->headerActions([
                Action::make('openPriceHistory')
                    ->label(__('Open price history'))
                    ->url(PriceHistory::getUrl())
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare),
            ])
            ->records(fn (): Collection => app(DashboardSupplierOverview::class)
                ->executiveLeaderboard(16)
                ->mapWithKeys(fn (object $row): array => [
                    $row->row_id => [
                        'id' => $row->row_id,
                        'supplier_label' => $row->supplier_label,
                        'catalog_products_quoted' => $row->catalog_products_quoted,
                        'comparable_products' => $row->comparable_products,
                        'best_price_wins' => $row->best_price_wins,
                        'best_price_share_pct' => $row->best_price_share_pct,
                        'rating_label' => $row->rating_label,
                        'rating_color' => $row->rating_color,
                        'price_vs_others_label' => $row->price_vs_others_label,
                    ],
                ]))
            ->columns([
                TextColumn::make('supplier_label')
                    ->label(__('Supplier'))
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('catalog_products_quoted')
                    ->label(__('Products quoted (catalog)'))
                    ->numeric()
                    ->alignment(Alignment::End)
                    ->description(__('How many catalog items they have priced')),
                TextColumn::make('price_vs_others_label')
                    ->label(__('Price competitiveness'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—')
                    ->alignment(Alignment::End)
                    ->description(__('Where another supplier quoted the same product')),
                TextColumn::make('rating_label')
                    ->label(__('Assessment'))
                    ->badge()
                    ->color(fn (array $record): string => (string) $record['rating_color']),
            ])
            ->paginated(false);
    }
}
