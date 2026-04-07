<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuotationAction;
use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\Concerns\InteractsWithQuotationDetailLayout;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

class ViewQuotation extends ViewRecord
{
    use InteractsWithQuotationDetailLayout;

    protected static string $resource = QuotationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'supplier',
            'items.mappedProduct',
            'ingestionBatch',
            'ingestionBatch.files' => fn ($query) => $query->orderBy('page_order'),
            'purchaseOrders' => fn ($query) => $query->orderBy('created_at'),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.resources.quotations.pages.view-quotation-detail')
                    ->viewData(function (): array {
                        $q = $this->getRecord();
                        $q->loadMissing([
                            'supplier',
                            'items.mappedProduct',
                            'ingestionBatch.files' => fn ($query) => $query->orderBy('page_order'),
                            'purchaseOrders' => fn ($query) => $query->orderBy('created_at'),
                        ]);

                        return [
                            'q' => $q,
                            'financial' => $this->quotationFinancialSummary($q),
                            'editUrl' => QuotationResource::getUrl('edit', ['record' => $q]),
                            'processTimeline' => $this->buildQuotationProcessTimeline($q),
                        ];
                    }),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(),
            DeleteAction::make()
                ->hidden(),
            Action::make('createPurchaseOrder')
                ->label(__('Create purchase order'))
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('success')
                ->hidden()
                ->requiresConfirmation()
                ->modalHeading(__('Create purchase order from this quotation?'))
                ->modalDescription(function (): string {
                    /** @var Quotation $q */
                    $q = $this->getRecord();

                    $base = __('A new PO will be created in draft status with lines copied from this quote. You can edit quantities, lines, and status afterwards.');

                    if ($q->supplier_id === null) {
                        return $base.' '.__('You must pick a catalog supplier on this quotation (Edit) first — POs require a linked supplier, not only the free-text name.');
                    }

                    return $base;
                })
                ->action(function (): void {
                    try {
                        $order = app(CreatePurchaseOrderFromQuotationAction::class)->execute($this->getRecord(), auth()->user());
                        Notification::make()->success()->title(__('Purchase order created'))->send();
                        $this->redirect(PurchaseOrderResource::getUrl('view', ['record' => $order]));
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Action::make('cloneToManualDraft')
                ->label(__('Clone to manual draft'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->hidden()
                ->requiresConfirmation()
                ->modalHeading(__('Create manual quotation draft?'))
                ->modalDescription(__('Copies supplier, header fields, and line items (including catalog product links) into a new manual-entry draft. Approval and batch/AI data are not copied.'))
                ->action(function (): void {
                    try {
                        $draft = app(CloneQuotationToManualDraftAction::class)->execute($this->getRecord(), auth()->user());
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

    public function getTitle(): string
    {
        /** @var Quotation $quotation */
        $quotation = $this->getRecord();

        return __('Quotation #:id', ['id' => $quotation->getKey()]);
    }

    /**
     * Duplicate a quotation line (pending quotations only; renumbers at end).
     */
    public function duplicateQuotationLine(int $quotationItemId): void
    {
        /** @var Quotation $quotation */
        $quotation = $this->getRecord();

        abort_unless(QuotationResource::canEdit($quotation), 403);

        if ($quotation->approved_at !== null) {
            Notification::make()
                ->warning()
                ->title(__('Cannot duplicate lines on an approved quotation'))
                ->send();

            return;
        }

        /** @var QuotationItem|null $item */
        $item = $quotation->items()->whereKey($quotationItemId)->first();

        if ($item === null) {
            Notification::make()
                ->danger()
                ->title(__('Line not found'))
                ->send();

            return;
        }

        $copy = $item->replicate();
        $nextLineNo = (int) $quotation->items()->max('line_no');
        $copy->line_no = $nextLineNo + 1;
        $copy->save();

        $quotation->unsetRelation('items');
        $quotation->load(['items.mappedProduct']);

        Notification::make()
            ->success()
            ->title(__('Line duplicated'))
            ->send();
    }

    /**
     * @return list<array{key: string, label: string, done: bool, at: ?Carbon, href: ?string}>
     */
    protected function buildQuotationProcessTimeline(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'purchaseOrders' => fn ($query) => $query->orderBy('created_at'),
        ]);

        $firstPo = $quotation->purchaseOrders->first();

        return [
            [
                'key' => 'created',
                'label' => __('Created'),
                'done' => true,
                'at' => $quotation->created_at,
                'href' => null,
            ],
            [
                'key' => 'approved',
                'label' => __('Approved'),
                'done' => $quotation->approved_at !== null,
                'at' => $quotation->approved_at,
                'href' => null,
            ],
            [
                'key' => 'po',
                'label' => __('PO created'),
                'done' => $firstPo !== null,
                'at' => $firstPo?->created_at,
                'href' => $firstPo !== null
                    ? PurchaseOrderResource::getUrl('view', ['record' => $firstPo])
                    : null,
            ],
        ];
    }
}
