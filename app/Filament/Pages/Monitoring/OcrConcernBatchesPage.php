<?php

namespace App\Filament\Pages\Monitoring;

use App\Exports\IngestionBatchesMonitoringExport;
use App\Filament\Actions\IngestionBatchRetryFilamentActions;
use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Filament\Tables\OperationalMonitoringFilters;
use App\Models\IngestionBatch;
use App\Services\Operations\IngestionBatchOperationalQuery;
use App\Services\Operations\IngestionBatchRetryPolicy;
use App\Support\Locale\VietnamesePresentation;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OcrConcernBatchesPage extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?int $navigationSort = 27;

    /** Hidden from sidebar; route still works if linked elsewhere. */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Monitoring: OCR issues');
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
        return IngestionBatchResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return __('OCR exceptions');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('OCR exceptions');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Batches where an image or PDF file is missing OCR, has empty text, or OCR confidence is below the internal partial threshold. Use row actions to queue targeted OCR retries when permitted.');
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
        return IngestionBatchOperationalQuery::ocrConcern(
            IngestionBatchOperationalQuery::base()
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('Batch'))
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('received_at')
                    ->label(__('Received'))
                    ->dateTime(VietnamesePresentation::DATETIME_FORMAT)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('file_count')
                    ->label(__('Files'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters(OperationalMonitoringFilters::ingestionBatchTable())
            ->recordActions([
                IngestionBatchRetryFilamentActions::retryOcrForBatch()
                    ->visible(fn (IngestionBatch $record): bool => IngestionBatchResource::canEdit($record)
                        && IngestionBatchRetryPolicy::canQueueOcrRetry($record)),
            ])
            ->recordUrl(fn (IngestionBatch $record): string => IngestionBatchResource::getUrl('view', ['record' => $record]));
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportOcrIssuesExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new IngestionBatchesMonitoringExport($this->getTableQueryForExport()->clone()),
                        'monitoring-ocr-issues-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => IngestionBatchResource::canViewAny()),
        ];
    }
}
