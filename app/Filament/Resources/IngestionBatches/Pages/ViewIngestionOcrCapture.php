<?php

namespace App\Filament\Resources\IngestionBatches\Pages;

use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewIngestionOcrCapture extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IngestionBatchResource::class;

    protected string $view = 'filament.resources.ingestion-batches.pages.view-ingestion-ocr-capture';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->mountCanAuthorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Raw OCR capture');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOcrPayload(): ?array
    {
        $this->getRecord()->loadMissing(['quotationReviewDraft']);
        $draft = $this->getRecord()->quotationReviewDraft;
        if ($draft === null || ! is_array($draft->payload_json)) {
            return null;
        }

        return $draft->payload_json;
    }
}
