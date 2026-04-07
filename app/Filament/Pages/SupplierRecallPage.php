<?php

namespace App\Filament\Pages;

use App\Actions\Supplier\LinkApprovedQuotationsToSuppliersByNameAction;
use App\Actions\Supplier\SyncSuppliersFromApprovedQuotationsAction;
use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Models\Quotation;
use App\Services\Supplier\SupplierMatchingService;
use App\Services\Supplier\SupplierRegistryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Tools to build the supplier catalog from approved quotation text and link FKs without editing stored names.
 */
class SupplierRecallPage extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 50;

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /**
     * @var ?string
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    #[Url(as: 'sort')]
    public ?string $tableSort = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return IngestionBatchResource::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('Supplier recall');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Supplier recall & catalog sync');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Supplier recall & catalog sync');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Build supplier master data from approved quotation names, then link supplier_id on quotations — supplier_name text is never changed.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCatalogFromQuotations')
                ->label(__('Sync catalog from quotations'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription(__('Creates supplier rows for each distinct supplier_name on approved quotations when no normalized match exists yet. Does not modify quotations.'))
                ->action(function (): void {
                    $result = app(SyncSuppliersFromApprovedQuotationsAction::class)->execute();
                    Notification::make()
                        ->success()
                        ->title(__('Catalog sync finished'))
                        ->body(__(':created new supplier(s), :skipped already in catalog, from :total distinct name(s).', [
                            'created' => $result['created'],
                            'skipped' => $result['already_existed'],
                            'total' => $result['distinct_names'],
                        ]))
                        ->send();
                }),
            Action::make('linkQuotationsToCatalog')
                ->label(__('Link quotations (supplier_id only)'))
                ->icon(Heroicon::OutlinedLink)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('Sets supplier_id on approved quotations where it is empty, matching suppliers.normalized_name to quotation supplier_name. Does not change supplier_name or line items.'))
                ->action(function (): void {
                    $result = app(LinkApprovedQuotationsToSuppliersByNameAction::class)->execute();
                    Notification::make()
                        ->success()
                        ->title(__('Linking finished'))
                        ->body(__(':updated quotation(s) linked, :skipped skipped (no catalog match), :examined examined.', [
                            'updated' => $result['updated'],
                            'skipped' => $result['skipped_no_match'],
                            'examined' => $result['examined'],
                        ]))
                        ->send();
                }),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $sub = Quotation::query()
            ->whereNotNull('approved_at')
            ->whereNull('supplier_id')
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->selectRaw('MIN(id) as id')
            ->addSelect('supplier_name')
            ->selectRaw('COUNT(*) as recall_count')
            ->groupBy('supplier_name');

        return Quotation::query()->fromSub($sub, 'quotations');
    }

    public function table(Table $table): Table
    {
        $matching = app(SupplierMatchingService::class);

        return $table
            ->queryStringIdentifier('supplierRecall')
            ->columns([
                TextColumn::make('supplier_name')
                    ->label(__('Supplier name (on quotation)'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('recall_count')
                    ->label(__('Quotations'))
                    ->numeric(),
                TextColumn::make('catalog_match')
                    ->label(__('Catalog match'))
                    ->state(function (Quotation $record) use ($matching): string {
                        $supplier = $matching->findByQuotationSupplierName((string) $record->supplier_name);

                        return $supplier !== null ? $supplier->name : '—';
                    }),
            ])
            ->defaultSort('supplier_name')
            ->recordActions([
                Action::make('ensureInCatalog')
                    ->label(__('Add to catalog'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->action(function (Quotation $record): void {
                        app(SupplierRegistryService::class)->findOrCreateByDisplayName((string) $record->supplier_name);
                        Notification::make()->success()->title(__('Supplier ensured in catalog'))->send();
                    }),
            ])
            ->emptyStateHeading(__('No unlinked approved quotations'))
            ->emptyStateDescription(__('All approved quotations already have a supplier_id, or none have supplier_name text.'));
    }
}
