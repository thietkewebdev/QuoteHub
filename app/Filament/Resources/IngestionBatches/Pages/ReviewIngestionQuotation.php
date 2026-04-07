<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Actions\Quotation\ApproveQuotationReviewAction;
use App\Actions\Quotation\RejectQuotationReviewAction;
use App\Actions\Quotation\RequestQuotationCorrectionsAction;
use App\Actions\Quotation\SaveQuotationReviewDraftAction;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Filament\Resources\IngestionBatches\Schemas\QuotationReviewForm;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ReviewIngestionQuotation extends EditRecord
{
    protected static string $resource = IngestionBatchResource::class;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return __('Review quotation');
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $batch = $this->getRecord()->loadMissing(['quotation', 'aiExtraction']);

        if ($batch->quotation) {
            $this->redirect(static::getResource()::getUrl('view', ['record' => $batch]));

            return;
        }

        if ($batch->aiExtraction === null) {
            Notification::make()
                ->danger()
                ->title(__('AI extraction required'))
                ->body(__('Run AI extraction before reviewing this batch.'))
                ->send();
            $this->redirect(static::getResource()::getUrl('view', ['record' => $batch]));

            return;
        }

        $this->fillForm();

        $this->previousUrl = url()->previous();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $data = app(QuotationReviewPayloadFactory::class)->forBatch($this->getRecord());
        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    public function form(Schema $schema): Schema
    {
        return QuotationReviewForm::configure($schema);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(SaveQuotationReviewDraftAction::class)->execute($record, auth()->user(), $data);

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
            ->title(__('Review draft saved'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveQuotation')
                ->label(__('Approve quotation'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Approve and create quotation?'))
                ->modalDescription(__('Creates final quotation and line items. AI extraction JSON is not modified.'))
                ->action(function (): void {
                    try {
                        $data = $this->form->getState();
                        app(ApproveQuotationReviewAction::class)->execute($this->getRecord(), auth()->user(), $data);
                        Notification::make()->success()->title(__('Quotation approved'))->send();
                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Action::make('requestCorrections')
                ->label(__('Request corrections'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->form([
                    Textarea::make('note')
                        ->label(__('Note to submitter'))
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $formData = $this->form->getState();
                        app(RequestQuotationCorrectionsAction::class)->execute(
                            $this->getRecord(),
                            auth()->user(),
                            $formData,
                            $data['note'] ?? null
                        );
                        Notification::make()->success()->title(__('Status updated'))->send();
                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Action::make('rejectReview')
                ->label(__('Reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label(__('Reason'))
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $formData = $this->form->getState();
                        app(RejectQuotationReviewAction::class)->execute(
                            $this->getRecord(),
                            auth()->user(),
                            $formData,
                            $data['reason'] ?? null
                        );
                        Notification::make()->success()->title(__('Review rejected'))->send();
                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->getRecord()]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        return __('Review quotation — batch #:id', ['id' => $this->getRecord()->getKey()]);
    }
}
