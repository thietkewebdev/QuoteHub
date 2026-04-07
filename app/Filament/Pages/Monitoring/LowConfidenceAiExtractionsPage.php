<?php

namespace App\Filament\Pages\Monitoring;

use App\Exports\LowConfidenceAiExtractionsExport;
use App\Filament\Actions\IngestionBatchRetryFilamentActions;
use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Filament\Tables\OperationalMonitoringFilters;
use App\Models\AiExtraction;
use App\Services\Operations\IngestionBatchRetryPolicy;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LowConfidenceAiExtractionsPage extends Page implements Tables\Contracts\HasTable
{
    use HasQuoteHubNavigationGroup;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 30;

    /** Hidden from sidebar; route still works if linked elsewhere. */
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Monitoring: AI confidence');
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

        $this->tableFilters = array_replace_recursive(
            $this->tableFilters ?? [],
            [
                'confidence_below' => [
                    'max' => data_get($this->tableFilters, 'confidence_below.max', 0.85),
                ],
            ],
        );

        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return IngestionBatchResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Low-confidence AI extractions');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Low-confidence AI extractions');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('AI extraction rows where overall confidence is below the threshold (default 85%). Use row actions to queue an AI retry when permitted.');
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
        return AiExtraction::query()
            ->join('ingestion_batches', 'ingestion_batches.id', '=', 'ai_extractions.ingestion_batch_id')
            ->select('ai_extractions.*')
            ->whereNotNull('ai_extractions.confidence_overall')
            ->with(['ingestionBatch.supplier']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('Extraction'))
                    ->sortable(),
                TextColumn::make('ingestion_batch_id')
                    ->label(__('Batch'))
                    ->sortable()
                    ->url(fn (AiExtraction $record): string => IngestionBatchResource::getUrl('view', ['record' => $record->ingestion_batch_id]))
                    ->color('primary'),
                TextColumn::make('ingestionBatch.supplier.name')
                    ->label(__('Supplier'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('ingestionBatch.received_at')
                    ->label(__('Batch received'))
                    ->dateTime(VietnamesePresentation::DATETIME_FORMAT)
                    ->sortable(['ingestion_batches.received_at', 'ai_extractions.id']),
                TextColumn::make('ingestionBatch.status')
                    ->label(__('Batch status'))
                    ->badge(),
                TextColumn::make('confidence_overall')
                    ->label(__('Overall confidence'))
                    ->formatStateUsing(fn ($state): ?string => $state === null ? null : QuotationLinePresentation::percent((float) $state * 100))
                    ->sortable(),
                TextColumn::make('model_name')
                    ->label(__('Model'))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ai_extractions.id', 'desc')
            ->filters(OperationalMonitoringFilters::aiExtractionMonitoringTable())
            ->recordActions([
                IngestionBatchRetryFilamentActions::retryAiForExtractionRow()
                    ->visible(function (AiExtraction $record): bool {
                        $batch = $record->ingestionBatch;
                        if ($batch === null) {
                            return false;
                        }

                        return IngestionBatchResource::canEdit($batch)
                            && IngestionBatchRetryPolicy::canQueueAiRetry($batch);
                    }),
            ])
            ->recordUrl(fn (AiExtraction $record): string => IngestionBatchResource::getUrl('view', ['record' => $record->ingestion_batch_id]));
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportLowConfidenceAiExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new LowConfidenceAiExtractionsExport($this->getTableQueryForExport()->clone()),
                        'monitoring-ai-low-confidence-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => IngestionBatchResource::canViewAny()),
        ];
    }
}
