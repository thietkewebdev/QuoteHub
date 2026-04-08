<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuotationAction;
use App\Actions\Quotation\CloneQuotationToManualDraftAction;
use App\Actions\Quotation\SetQuotationItemProductMappingAction;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\Concerns\InteractsWithQuotationDetailLayout;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        $relationManagers = $this->getRelationManagersContentComponent();
        $relationManagers->columnSpanFull();

        return $schema
            ->components([
                SchemaView::make('filament.resources.quotations.pages.view-quotation-detail')
                    ->columnSpanFull()
                    ->viewData(function (): array {
                        $q = $this->getRecord();
                        $q->loadMissing([
                            'supplier',
                            'items.mappedProduct',
                            'ingestionBatch',
                            'ingestionBatch.files' => fn ($query) => $query->orderBy('page_order'),
                            'purchaseOrders' => fn ($query) => $query->orderBy('created_at'),
                        ]);

                        $ingestionFile = $q->ingestionBatch?->files->first();

                        return [
                            'q' => $q,
                            'financial' => $this->quotationFinancialSummary($q),
                            'editUrl' => QuotationResource::getUrl('edit', ['record' => $q]),
                            'canEditQuotationLines' => QuotationResource::canEdit($q),
                            'quotationFileDownloadUrl' => $ingestionFile !== null
                                ? route('ingestion.files.download', ['ingestion_file' => $ingestionFile])
                                : null,
                        ];
                    }),
                $relationManagers,
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
            $this->mapQuotationLineItemAction(),
            $this->unlinkQuotationLineItemAction(),
        ];
    }

    protected function mapQuotationLineItemAction(): Action
    {
        return Action::make('mapQuotationLineItem')
            ->label(__('Map'))
            ->hidden()
            ->modalHeading(function (Action $action): string {
                $item = $this->resolveQuotationItemForMappingAction($action);

                return $item !== null && $item->mapped_product_id !== null
                    ? __('Remap line to product')
                    : __('Map line to product');
            })
            ->modalDescription(__('Search by product name or SKU. Raw line fields are not changed. Each save is recorded in the mapping audit log.'))
            ->modalSubmitActionLabel(__('Save mapping'))
            ->modalWidth('lg')
            ->fillForm(function (Action $action): array {
                $item = $this->resolveQuotationItemForMappingAction($action);

                return [
                    'product_id' => $item?->mapped_product_id,
                ];
            })
            ->schema(function (Action $action): array {
                return [
                    Select::make('product_id')
                        ->label(__('Product'))
                        ->placeholder(__('Select a product'))
                        ->native(false)
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search): array => $this->searchProductsForLineMapping($search))
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $p = Product::query()->whereKey($value)->first();
                            if ($p === null) {
                                return null;
                            }
                            $sku = $p->sku !== null && $p->sku !== '' ? ' ('.$p->sku.')' : '';

                            return $p->name.$sku;
                        }),
                ];
            })
            ->action(function (array $data, Action $action): void {
                $this->saveQuotationLineProductMapping($action, $data['product_id'] ?? null);
            })
            ->authorize(fn (): bool => QuotationResource::canEdit($this->getRecord()));
    }

    protected function unlinkQuotationLineItemAction(): Action
    {
        return Action::make('unlinkQuotationLineItem')
            ->label(__('Unlink'))
            ->hidden()
            ->color('danger')
            ->modalHeading(__('Unlink product mapping?'))
            ->modalDescription(__('The line stays on the quotation; only the catalog product link is removed.'))
            ->modalSubmitActionLabel(__('Unlink'))
            ->requiresConfirmation()
            ->action(function (Action $action): void {
                $this->saveQuotationLineProductMapping($action, null);
            })
            ->authorize(fn (): bool => QuotationResource::canEdit($this->getRecord()));
    }

    protected function resolveQuotationItemForMappingAction(Action $action): ?QuotationItem
    {
        $id = (int) ($action->getArguments()['quotationItemId'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        /** @var Quotation $quotation */
        $quotation = $this->getRecord();

        return $quotation->items()->whereKey($id)->first();
    }

    /**
     * @return array<int|string, string>
     */
    protected function searchProductsForLineMapping(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($escaped): void {
                $q->where('name', 'like', '%'.$escaped.'%')
                    ->orWhere('sku', 'like', '%'.$escaped.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->mapWithKeys(function (Product $p): array {
                $sku = $p->sku !== null && $p->sku !== '' ? ' ('.$p->sku.')' : '';

                return [$p->id => $p->name.$sku];
            })
            ->all();
    }

    protected function saveQuotationLineProductMapping(Action $action, mixed $rawProductId): void
    {
        $item = $this->resolveQuotationItemForMappingAction($action);

        if ($item === null) {
            Notification::make()
                ->danger()
                ->title(__('Line not found'))
                ->send();

            return;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            Notification::make()
                ->danger()
                ->title(__('You must be signed in.'))
                ->send();

            return;
        }

        $productId = $rawProductId === null || $rawProductId === '' ? null : (int) $rawProductId;

        try {
            app(SetQuotationItemProductMappingAction::class)->execute($item, $user, $productId);
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title($e->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($productId === null ? __('Mapping removed') : __('Mapping saved'))
            ->send();

        /** @var Quotation $quotation */
        $quotation = $this->getRecord();
        $quotation->unsetRelation('items');
        $quotation->load(['items.mappedProduct']);
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
}
