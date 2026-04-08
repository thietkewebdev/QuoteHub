<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\PriceHistory;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Operations\DashboardMappedProductBestPrices;
use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Dashboard quick lookup: search catalog products by name or SKU and see best recorded unit price (excl. VAT).
 */
final class ProductDashboardPriceSearchWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operations.product-price-table-widget';

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
            ->heading($this->mainHeading())
            ->description($this->mainDescription())
            ->searchable()
            ->searchPlaceholder(__('Name, SKU, or technical specifications…'))
            ->headerActions([
                Action::make('openPriceHistory')
                    ->label(__('Open price history'))
                    ->url(PriceHistory::getUrl())
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (): bool => PriceHistory::canAccess()),
            ])
            ->emptyStateHeading(__('No products'))
            ->emptyStateDescription(__('No active catalog products match your search, or the catalog is empty.'))
            ->emptyStateIcon(Heroicon::OutlinedMagnifyingGlass)
            ->recordClasses('transition-colors duration-150 ease-out hover:bg-emerald-500/[0.06] dark:hover:bg-emerald-400/[0.07]')
            ->records(function (): Collection {
                $rows = app(DashboardMappedProductBestPrices::class)
                    ->catalogLookupRows($this->getTableSearch(), 25);

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
                            'specs_text' => $row->specs_text ?? null,
                        ],
                    ];
                });
            })
            ->columns([
                TextColumn::make('product_sku')
                    ->label(__('SKU'))
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('product_name')
                    ->label(__('Product'))
                    ->wrap()
                    ->url(fn (array $record): string => ProductResource::getUrl('view', ['record' => $record['product_id']])),
                TextColumn::make('best_unit_price')
                    ->label(__('Best unit price (excl. VAT)'))
                    ->placeholder('—')
                    ->weight(FontWeight::Bold)
                    ->color(fn ($state): ?string => $state !== null ? 'success' : null)
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('best_price_badge')
                    ->label('')
                    ->badge()
                    ->color('success')
                    ->state(fn (array $record): ?string => $record['best_unit_price'] !== null ? __('Best price') : null),
                TextColumn::make('specs_text')
                    ->label(__('Technical specifications'))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(160),
                TextColumn::make('best_supplier_name')
                    ->label(__('Supplier on that quote'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->paginated(false);
    }

    private function mainHeading(): string|Htmlable
    {
        return new HtmlString(
            '<span class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">'
            .e(__('Quick product price lookup'))
            .'</span>'
        );
    }

    private function mainDescription(): string|Htmlable
    {
        return new HtmlString(
            '<span class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">'
            .e(__('Lists active catalog products by name (like the Products page). Shows the lowest ex-VAT unit price from approved lines when available. Search matches name, SKU, or technical specifications.'))
            .'</span>'
        );
    }
}
