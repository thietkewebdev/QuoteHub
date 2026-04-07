<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuotationAction;
use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Throwable;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'supplier',
            'ingestionBatch',
            'ingestionBatch.files' => fn ($query) => $query->orderBy('page_order'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            Action::make('createPurchaseOrder')
                ->label(__('Create purchase order'))
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->approved_at !== null
                    && $this->getRecord()->items()->exists())
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
                ->visible(fn (): bool => $this->getRecord()->approved_at !== null)
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
}
