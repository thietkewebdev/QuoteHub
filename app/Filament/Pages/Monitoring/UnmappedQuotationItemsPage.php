<?php

namespace App\Filament\Pages\Monitoring;

use App\Exports\UnmappedQuotationLinesExport;
use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Tables\OperationalMonitoringFilters;
use App\Models\QuotationItem;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\QuotationLinePresentation;
use BackedEnum;
use Filament\Actions\Action;
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
use Maatwebsite\Excel\Facades\Excel;

class UnmappedQuotationItemsPage extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLinkSlash;

    protected static ?int $navigationSort = 31;

    /** Hidden from sidebar; route still works if linked elsewhere. */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Monitoring: Unmapped lines');
    }

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
        return QuotationResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Unmapped quotation lines');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Unmapped quotation lines');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Approved quotation line items without a canonical product mapping. Read-only.');
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
        return QuotationItem::query()
            ->whereNull('mapped_product_id')
            ->whereHas('quotation', fn (Builder $q): Builder => $q->whereNotNull('approved_at'))
            ->with(['quotation.supplier', 'quotation.ingestionBatch']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_id')
                    ->label(__('Quotation'))
                    ->sortable()
                    ->url(fn (QuotationItem $record): string => QuotationResource::getUrl('view', ['record' => $record->quotation_id]))
                    ->color('primary'),
                TextColumn::make('quotation.supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('quotation.approved_at')
                    ->label(__('Approved at'))
                    ->dateTime(VietnamesePresentation::DATETIME_FORMAT),
                TextColumn::make('raw_name')
                    ->label(__('Raw name'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('raw_model')
                    ->label(__('Raw model'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('brand')
                    ->label(__('Brand'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('line_total')
                    ->label(__('Line total'))
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('vat_percent')
                    ->label(__('VAT %'))
                    ->formatStateUsing(fn ($state): ?string => QuotationLinePresentation::percent($state)),
            ])
            ->defaultSort('quotation_id', 'desc')
            ->filters(OperationalMonitoringFilters::unmappedQuotationItemTable())
            ->recordUrl(fn (QuotationItem $record): string => QuotationResource::getUrl('view', ['record' => $record->quotation_id]));
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportUnmappedLinesExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new UnmappedQuotationLinesExport($this->getTableQueryForExport()->clone()),
                        'monitoring-unmapped-lines-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => QuotationResource::canViewAny()),
        ];
    }
}
