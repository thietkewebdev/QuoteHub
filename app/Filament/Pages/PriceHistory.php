<?php

namespace App\Filament\Pages;

use App\Exports\PriceHistoryLinesExport;
use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Tables\PriceHistoryTable;
use App\Services\Quotation\PriceHistoryQuery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PriceHistory extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 5;

    #[Url(as: 'reordering')]
    public bool $isTableReordering = false;

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    #[Url(as: 'grouping')]
    public ?string $tableGrouping = null;

    /**
     * @var ?string
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    #[Url(as: 'sort')]
    public ?string $tableSort = null;

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return QuotationResource::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('Price history');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Price history');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Price history');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Compare unit prices by supplier and quote date. Rows are grouped by catalog product, model code, or quoted name + brand.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return PriceHistoryQuery::make();
    }

    public function table(Table $table): Table
    {
        return PriceHistoryTable::configure($table);
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportPriceHistoryExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new PriceHistoryLinesExport($this->getTableQueryForExport()->clone()),
                        'price-history-lines-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => QuotationResource::canViewAny()),
        ];
    }
}
