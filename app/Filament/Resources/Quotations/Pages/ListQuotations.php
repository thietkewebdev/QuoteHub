<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Exports\ApprovedQuotationItemsExport;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationReviewDraft;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createQuotation')
                ->label(__('Create quotation'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->modalHeading(__('Create quotation'))
                ->modalDescription(__('Choose how to add a quotation.'))
                ->modalWidth(Width::Medium)
                ->modalSubmitAction(false)
                ->modalFooterActions([
                    Action::make('chooseManual')
                        ->label(__('Enter manually'))
                        ->color('primary')
                        ->visible(fn (): bool => ManualQuotationEntryResource::canViewAny())
                        ->action(function (): void {
                            $draft = QuotationReviewDraft::query()->create([
                                'ingestion_batch_id' => null,
                                'ai_extraction_id' => null,
                                'payload_json' => app(QuotationReviewPayloadFactory::class)->emptyPayload(),
                                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
                            ]);
                            $this->redirect(ManualQuotationEntryResource::getUrl('edit', ['record' => $draft]));
                        }),
                    Action::make('choosePdf')
                        ->label(__('Upload quotation PDF'))
                        ->color('gray')
                        ->outlined()
                        ->visible(fn (): bool => IngestionBatchResource::canCreate())
                        ->url(IngestionBatchResource::getUrl('create'))
                        ->close(),
                    Action::make('dismissCreateQuotationModal')
                        ->label(__('Close'))
                        ->color('gray')
                        ->outlined()
                        ->close(),
                ])
                ->visible(fn (): bool => ManualQuotationEntryResource::canViewAny() || IngestionBatchResource::canCreate()),
            Action::make('cloneFromQuotation')
                ->label(__('Clone from quotation'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->outlined()
                ->modalHeading(__('Clone an approved quotation'))
                ->modalDescription(__('Search by supplier name, quote number, or ID. Header, lines, and product mappings are copied into a new manual draft.'))
                ->form([
                    Select::make('quotation_id')
                        ->label(__('Approved quotation'))
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return Quotation::query()
                                ->whereNotNull('approved_at')
                                ->where(function ($q) use ($search): void {
                                    $q->where('supplier_name', 'like', '%'.$search.'%')
                                        ->orWhere('supplier_quote_number', 'like', '%'.$search.'%');
                                    if (is_numeric($search)) {
                                        $q->orWhere('id', (int) $search);
                                    }
                                })
                                ->orderByDesc('approved_at')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Quotation $q): array => [
                                    $q->id => '#'.$q->id.' — '.$q->supplier_name,
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if ($value === null || $value === '') {
                                return null;
                            }
                            $q = Quotation::query()->find((int) $value);

                            return $q instanceof Quotation ? '#'.$q->id.' — '.$q->supplier_name : null;
                        }),
                ])
                ->action(function (array $data): void {
                    try {
                        $quotation = Quotation::query()
                            ->whereNotNull('approved_at')
                            ->find((int) ($data['quotation_id'] ?? 0));
                        if ($quotation === null) {
                            Notification::make()->danger()->title(__('Quotation not found.'))->send();

                            return;
                        }
                        $draft = app(CloneQuotationToManualDraftAction::class)->execute($quotation, auth()->user());
                        Notification::make()->success()->title(__('Manual draft created'))->send();
                        $this->redirect(ManualQuotationEntryResource::getUrl('edit', ['record' => $draft]));
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                })
                ->visible(fn (): bool => ManualQuotationEntryResource::canViewAny()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportApprovedQuotationItemsExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalDescription(__('Only lines from approved quotations are exported. The current table filters and search still limit which quotations are included.'))
                ->action(function (): BinaryFileResponse {
                    $quotationIdsQuery = $this->getTableQueryForExport()->clone()->select('quotations.id');

                    $itemsQuery = QuotationItem::query()
                        ->whereIn('quotation_id', $quotationIdsQuery)
                        ->whereHas('quotation', fn (Builder $q): Builder => $q->whereNotNull('approved_at'))
                        ->with(['quotation', 'mappedProduct'])
                        ->orderBy('quotation_id')
                        ->orderBy('line_no');

                    return Excel::download(
                        new ApprovedQuotationItemsExport($itemsQuery),
                        'quotation-items-approved-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => QuotationResource::canViewAny()),
        ];
    }
}
