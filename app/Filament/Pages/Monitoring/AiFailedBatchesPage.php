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

class AiFailedBatchesPage extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 28;

    /** Hidden from sidebar; route still works if linked elsewhere. */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Monitoring: AI failed');
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
        return __('AI failed batches');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('AI failed batches');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Ingestion batches whose AI extraction job failed. Read-only.');
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
        return IngestionBatchOperationalQuery::base()
            ->where('ingestion_batches.status', 'ai_failed');
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
                IngestionBatchRetryFilamentActions::retryAiForBatch()
                    ->visible(fn (IngestionBatch $record): bool => IngestionBatchResource::canEdit($record)
                        && IngestionBatchRetryPolicy::canQueueAiRetry($record)),
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
            Action::make('exportAiFailedExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new IngestionBatchesMonitoringExport($this->getTableQueryForExport()->clone()),
                        'monitoring-ai-failed-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => IngestionBatchResource::canViewAny()),
        ];
    }
}
