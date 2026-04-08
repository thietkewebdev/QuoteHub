<?php

namespace App\Filament\Resources\IngestionBatches\Schemas;

use App\Models\IngestionBatch;
use App\Support\Ingestion\IngestionBatchPipelineProgressPresenter;
use App\Support\Locale\VietnamesePresentation;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class IngestionBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Batch details'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Batch ID')),
                        TextEntry::make('source_channel')
                            ->label(__('Source channel')),
                        TextEntry::make('supplier.name')
                            ->label(__('Supplier'))
                            ->placeholder('—'),
                        TextEntry::make('received_at')
                            ->label(__('Received at'))
                            ->dateTime(VietnamesePresentation::DATETIME_FORMAT),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->formatStateUsing(fn (?string $state): string => IngestionBatch::localizedStatusLabel($state))
                            ->badge()
                            ->color(fn (?string $state): string => IngestionBatch::statusBadgeColor($state)),
                        TextEntry::make('pipeline_progress')
                            ->label(__('Pipeline progress'))
                            ->visible(fn (IngestionBatch $record): bool => in_array($record->status, ['preprocessing', 'ai_processing'], true))
                            ->html()
                            ->columnSpanFull()
                            ->formatStateUsing(function (TextEntry $component, $state): HtmlString {
                                $record = $component->getRecord();
                                if (! $record instanceof IngestionBatch) {
                                    return new HtmlString('');
                                }

                                return IngestionBatchPipelineProgressPresenter::infolistProgressHtml($record);
                            }),
                        TextEntry::make('file_count')
                            ->label(__('File count'))
                            ->numeric(),
                        TextEntry::make('overall_confidence')
                            ->label(__('Overall confidence'))
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state) => $state === null ? null : (string) $state),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime(VietnamesePresentation::DATETIME_FORMAT),
                    ])
                    ->columns(2),
                Section::make(__('AI extraction'))
                    ->extraAttributes(['id' => 'quotation-ai-extraction'])
                    ->description(function (): ?string {
                        $driver = strtolower((string) config('quotation_ai.driver', 'openai'));

                        return $driver === 'mock'
                            ? __('Mock quotation driver is active (QUOTATION_AI_DRIVER=mock). OCR is not sent to OpenAI; re-run extraction after switching to openai.')
                            : null;
                    })
                    ->schema([
                        TextEntry::make('ai_mock_driver_badge')
                            ->label(__('Extraction driver'))
                            ->badge()
                            ->color('warning')
                            ->state(fn (): ?string => strtolower((string) config('quotation_ai.driver', 'openai')) === 'mock' ? __('MOCK — not OpenAI') : __('OpenAI'))
                            ->columnSpanFull(),
                        TextEntry::make('ai_supplier_profile_mode')
                            ->label(__('Supplier profile mode'))
                            ->visible(fn (IngestionBatch $record): bool => $record->aiExtraction !== null)
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'inferred' => 'info',
                                'confirmed' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'inferred' => __('Inferred from OCR'),
                                'confirmed' => __('Confirmed (batch supplier)'),
                                default => __('No profile'),
                            })
                            ->state(fn (IngestionBatch $record): ?string => $record->aiExtraction?->supplier_profile_mode),
                        TextEntry::make('ai_supplier_profile_detail')
                            ->label(__('Supplier profile detail'))
                            ->visible(fn (IngestionBatch $record): bool => $record->aiExtraction !== null)
                            ->columnSpanFull()
                            ->state(function (IngestionBatch $record): string {
                                $ai = $record->aiExtraction;
                                if ($ai === null) {
                                    return '—';
                                }

                                $mode = $ai->supplier_profile_mode ?? 'none';
                                if ($mode === 'none') {
                                    return __('No supplier-specific extraction profile was applied (not confirmed on the batch and inference did not meet the threshold).');
                                }

                                if ($mode === 'confirmed') {
                                    $supplierLabel = $record->supplier?->name ?? __('Unknown');
                                    $hasProfile = $ai->supplierExtractionProfile !== null;
                                    $inf = is_array($ai->supplier_profile_inference) ? $ai->supplier_profile_inference : [];
                                    $conf = $inf['inference_confidence'] ?? null;
                                    $confStr = $conf !== null ? (string) round((float) $conf, 2) : '—';

                                    return $hasProfile
                                        ? __('Batch supplier ":name" is confirmed; saved extraction hints were included in the prompt. Supplier inference confidence: :conf.', ['name' => $supplierLabel, 'conf' => $confStr])
                                        : __('Batch supplier ":name" is confirmed; there is no enabled extraction profile—only the catalog supplier name was passed to the model. Supplier inference confidence: :conf.', ['name' => $supplierLabel, 'conf' => $confStr]);
                                }

                                $name = $ai->supplierExtractionProfile?->supplier?->name ?? __('Unknown supplier');
                                $inf = is_array($ai->supplier_profile_inference) ? $ai->supplier_profile_inference : [];
                                $score = $inf['score_raw'] ?? null;
                                $conf = $inf['inference_confidence'] ?? null;
                                $terms = $inf['matched_terms'] ?? [];
                                $termsStr = is_array($terms) && $terms !== []
                                    ? implode(', ', array_map(fn (mixed $t): string => (string) $t, $terms))
                                    : '—';

                                return __('Inferred supplier: :name. Internal match score :score. Normalized inference confidence :conf. OCR signals matched: :terms.', [
                                    'name' => $name,
                                    'score' => $score !== null ? (string) round((float) $score, 2) : '—',
                                    'conf' => $conf !== null ? (string) round((float) $conf, 2) : '—',
                                    'terms' => $termsStr,
                                ]);
                            }),
                        TextEntry::make('ai_mock_provider_banner')
                            ->label('')
                            ->color('warning')
                            ->state(fn (): ?string => strtolower((string) config('quotation_ai.driver', 'openai')) === 'mock'
                                ? __('Mock driver: summary fields below are not from a language model. Set QUOTATION_AI_DRIVER=openai and OPENAI_API_KEY, then run AI extraction again.')
                                : null)
                            ->visible(fn (): bool => strtolower((string) config('quotation_ai.driver', 'openai')) === 'mock')
                            ->columnSpanFull(),
                        TextEntry::make('ai_extraction_engine')
                            ->label(__('Extraction engine'))
                            ->visible(fn (IngestionBatch $record): bool => $record->aiExtraction !== null)
                            ->state(function (IngestionBatch $record): ?string {
                                $meta = data_get($record->aiExtraction?->extraction_json, 'extraction_meta');
                                if (! is_array($meta)) {
                                    return null;
                                }
                                $v = (string) ($meta['engine_version'] ?? '');
                                $p = (int) ($meta['pass_count'] ?? 0);

                                return $v !== '' ? $v.' · '.__(':count passes', ['count' => $p]) : null;
                            })
                            ->placeholder('—'),
                        TextEntry::make('ai_summary_supplier')
                            ->label(__('Supplier (extracted)'))
                            ->state(fn (IngestionBatch $record): ?string => data_get($record->aiExtraction?->extraction_json, 'quotation_header.supplier_name') ?: null)
                            ->placeholder('—'),
                        TextEntry::make('ai_summary_quote_date')
                            ->label(__('Quote date (extracted)'))
                            ->state(fn (IngestionBatch $record): ?string => data_get($record->aiExtraction?->extraction_json, 'quotation_header.quote_date') ?: null)
                            ->formatStateUsing(fn (?string $state): ?string => VietnamesePresentation::dateFromString($state))
                            ->placeholder('—'),
                        TextEntry::make('ai_summary_total')
                            ->label(__('Total amount (extracted)'))
                            ->state(fn (IngestionBatch $record): mixed => ($v = data_get($record->aiExtraction?->extraction_json, 'quotation_header.total_amount')) !== null && $v !== '' ? $v : null)
                            ->formatStateUsing(fn (mixed $state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('ai_summary_item_count')
                            ->label(__('Line items'))
                            ->state(fn (IngestionBatch $record): ?int => ($items = data_get($record->aiExtraction?->extraction_json, 'items')) !== null && is_array($items) ? count($items) : null)
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('ai_summary_confidence')
                            ->label(__('Overall confidence (extracted)'))
                            ->state(fn (IngestionBatch $record): ?string => ($v = data_get($record->aiExtraction?->extraction_json, 'overall_confidence')) !== null && $v !== ''
                                ? (string) $v
                                : null)
                            ->placeholder('—'),
                        Section::make(__('Full extraction'))
                            ->collapsed()
                            ->schema([
                                CodeEntry::make('ai_extraction_json')
                                    ->label(__('extraction_json'))
                                    ->state(fn (IngestionBatch $record): ?array => $record->aiExtraction?->extraction_json)
                                    ->grammar('json')
                                    ->jsonFlags(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (IngestionBatch $record): bool => $record->aiExtraction !== null),
                    ])
                    ->columns(2),
                Section::make(__('Approved quotation'))
                    ->description(__('Final record created at approval. Raw AI extraction JSON above is unchanged.'))
                    ->schema([
                        TextEntry::make('quotation.id')
                            ->label(__('Quotation ID'))
                            ->numeric(),
                        TextEntry::make('quotation.supplier_name')
                            ->label(__('Supplier (approved)'))
                            ->placeholder('—'),
                        TextEntry::make('quotation.supplier_quote_number')
                            ->label(__('Supplier quote #'))
                            ->placeholder('—'),
                        TextEntry::make('quotation.total_amount')
                            ->label(__('Total (approved)'))
                            ->formatStateUsing(fn (mixed $state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('quotation.approved_at')
                            ->label(__('Approved at'))
                            ->dateTime(VietnamesePresentation::DATETIME_FORMAT)
                            ->placeholder('—'),
                        TextEntry::make('quotation.approver.name')
                            ->label(__('Approved by'))
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visible(fn (IngestionBatch $record): bool => $record->quotation !== null),
                Section::make(__('Audit'))
                    ->collapsed()
                    ->schema([
                        TextEntry::make('uploader.name')
                            ->label(__('Uploaded by'))
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label(__('Last updated'))
                            ->dateTime(VietnamesePresentation::DATETIME_FORMAT),
                    ])
                    ->columns(2),
            ]);
    }
}
