<?php

namespace App\Filament\Resources\ManualQuotationEntries\Pages;

use App\Actions\Quotation\ApproveManualQuotationAction;
use App\Actions\Quotation\SaveManualQuotationDraftAction;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\QuotationReviewDraft;
use App\Services\Quotation\LineItemsPasteParser;
use App\Services\Quotation\ManualQuotationPayloadEnricher;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class EditManualQuotationEntry extends EditRecord
{
    protected static string $resource = ManualQuotationEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->approved_quotation_id !== null) {
            $this->redirect(QuotationResource::getUrl('view', ['record' => $this->getRecord()->approved_quotation_id]));
        }
    }

    public function form(Schema $schema): Schema
    {
        return ManualQuotationEntryResource::form($schema);
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $payload = $this->getRecord()->payload_json;
        if (! is_array($payload)) {
            $payload = app(QuotationReviewPayloadFactory::class)->emptyPayload();
        }
        $this->form->fill($payload);

        $this->callHook('afterFill');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof QuotationReviewDraft) {
            return $record;
        }

        app(SaveManualQuotationDraftAction::class)->execute($record, auth()->user(), $data);

        return $record->refresh();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('Draft saved'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pasteLineItems')
                ->label(__('Paste from Excel'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->modalHeading(__('Paste rows from spreadsheet'))
                ->modalDescription(
                    __('Paste cells copied from Excel (tab-separated). Column order: product name, model, brand, unit, quantity, unit price (excl. VAT), VAT %, line subtotal (excl. VAT), specs. Missing columns at the end are OK. Vietnamese numbers like 1.234.567 are accepted.')
                )
                ->modalSubmitActionLabel(__('Apply to line items'))
                ->form([
                    Textarea::make('raw')
                        ->label(__('Clipboard data'))
                        ->rows(10)
                        ->required(),
                    Checkbox::make('replace_existing')
                        ->label(__('Replace existing lines'))
                        ->default(false),
                    Checkbox::make('skip_first_line')
                        ->label(__('Skip first row (header)'))
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $parser = app(LineItemsPasteParser::class);
                    $parsed = $parser->parse((string) ($data['raw'] ?? ''), (bool) ($data['skip_first_line'] ?? false));
                    if ($parsed === []) {
                        Notification::make()
                            ->warning()
                            ->title(__('No rows parsed'))
                            ->body(__('Check that the first column is the product name and rows are tab-separated.'))
                            ->send();

                        return;
                    }
                    $state = $this->form->getState();
                    $items = ($data['replace_existing'] ?? false)
                        ? $parsed
                        : array_merge(array_values($state['items'] ?? []), $parsed);
                    $state['items'] = $items;
                    $state = app(ManualQuotationPayloadEnricher::class)->enrich($state);
                    $this->form->fill($state);
                    Notification::make()
                        ->success()
                        ->title(($data['replace_existing'] ?? false)
                            ? __('Loaded :n line(s)', ['n' => count($parsed)])
                            : __('Added :n line(s)', ['n' => count($parsed)]))
                        ->send();
                }),
            Action::make('approveQuotation')
                ->label(__('Approve quotation'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Approve and create quotation?'))
                ->modalDescription(__('Creates the final quotation and line items. This does not use AI or ingestion batches.'))
                ->action(function (): void {
                    try {
                        $data = $this->form->getState();
                        $quotation = app(ApproveManualQuotationAction::class)->execute(
                            $this->getRecord(),
                            auth()->user(),
                            $data
                        );
                        Notification::make()->success()->title(__('Quotation approved'))->send();
                        $this->redirect(QuotationResource::getUrl('view', ['record' => $quotation]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('Manual quotation — draft #:id', ['id' => $this->getRecord()->getKey()]);
    }
}
