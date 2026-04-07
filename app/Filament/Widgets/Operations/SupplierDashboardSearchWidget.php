<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\PriceHistory;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Services\Operations\DashboardSupplierSearch;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Dashboard quick lookup: search catalog suppliers by name or code.
 */
final class SupplierDashboardSearchWidget extends TableWidget
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
        return SupplierResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Quick supplier lookup'))
            ->description(__('Up to :max suppliers are listed; type in the search box to filter by name or code. Click a supplier to open their page (contacts, details). Click the quotation count to search quotations by that name.', ['max' => 100]))
            ->searchable()
            ->searchPlaceholder(__('Supplier name or code…'))
            ->headerActions([
                Action::make('openSuppliers')
                    ->label(__('Suppliers'))
                    ->url(SupplierResource::getUrl())
                    ->icon(Heroicon::OutlinedBuildingOffice2),
                Action::make('openPriceHistory')
                    ->label(__('Open price history'))
                    ->url(PriceHistory::getUrl())
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (): bool => PriceHistory::canAccess()),
            ])
            ->emptyStateHeading(__('No suppliers'))
            ->emptyStateDescription(__('Add suppliers from supplier recall / catalog sync, or create them when needed.'))
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->records(function (): Collection {
                // Read search from the Livewire table state (Filament’s injected `$search` arg is unreliable on some widgets).
                $suppliers = app(DashboardSupplierSearch::class)->search($this->getTableSearch());

                return $suppliers->mapWithKeys(function (Supplier $supplier): array {
                    $id = (string) $supplier->getKey();

                    return [
                        $id => [
                            'id' => $id,
                            'supplier_id' => (int) $supplier->getKey(),
                            'name' => $supplier->name,
                            'code' => $supplier->code,
                            'phone' => $supplier->phone,
                            'approved_quotations_count' => (int) ($supplier->approved_quotations_count ?? 0),
                        ],
                    ];
                });
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('Supplier'))
                    ->wrap()
                    ->url(fn (array $record): string => SupplierResource::getUrl('view', ['record' => $record['supplier_id']])),
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('—'),
                TextColumn::make('approved_quotations_count')
                    ->label(__('Approved quotations'))
                    ->numeric()
                    ->url(fn (array $record): string => $this->quotationsSearchUrl((string) $record['name'])),
            ])
            ->paginated(false);
    }

    /**
     * List quotations page global search matches supplier name (among other fields).
     */
    private function quotationsSearchUrl(string $term): string
    {
        $base = QuotationResource::getUrl('index');

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query([
            'search' => $term,
        ]);
    }
}
