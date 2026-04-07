<?php

namespace App\Filament\Resources\ManualQuotationEntries\Pages;

use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Models\Quotation;
use App\Models\QuotationReviewDraft;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Throwable;

class ListManualQuotationEntries extends ListRecords
{
    protected static string $resource = ManualQuotationEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newManualQuotation')
                ->label(__('New manual quotation'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->action(function (): void {
                    $draft = QuotationReviewDraft::query()->create([
                        'ingestion_batch_id' => null,
                        'ai_extraction_id' => null,
                        'payload_json' => app(QuotationReviewPayloadFactory::class)->emptyPayload(),
                        'review_status' => QuotationReviewDraft::STATUS_DRAFT,
                    ]);
                    $this->redirect(ManualQuotationEntryResource::getUrl('edit', ['record' => $draft]));
                }),
            Action::make('cloneFromQuotation')
                ->label(__('Clone from quotation'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
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
                }),
        ];
    }
}
