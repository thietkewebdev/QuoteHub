<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\IngestionFile;
use App\Models\Quotation;
use App\Support\Locale\VietnamesePresentation;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class QuotationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Quotation header'))
                    ->description(__('Supplier, dates, and contact — totals and notes below.'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->iconColor('primary')
                    ->schema([
                        TextEntry::make('supplier_name')
                            ->label(__('Supplier name'))
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->iconColor('gray')
                            ->weight(FontWeight::Medium)
                            ->placeholder('—'),
                        TextEntry::make('quote_date')
                            ->label(__('Quote date'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->iconColor('gray')
                            ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT))
                            ->placeholder('—'),
                        TextEntry::make('contact_person')
                            ->label(__('Contact person'))
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->iconColor('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::SemiBold)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('subtotal_before_tax')
                            ->label(__('Subtotal (before tax)'))
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('total_amount')
                            ->label(__('Total amount'))
                            ->alignEnd()
                            ->weight(FontWeight::SemiBold)
                            ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state))
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('Policy & status'))
                    ->description(__('Pricing policy tags, validity window, and approval lifecycle.'))
                    ->icon(Heroicon::OutlinedTag)
                    ->iconColor('gray')
                    ->schema([
                        TextEntry::make('lifecycle_status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (Quotation $record): string => $record->approvalStatusColor())
                            ->state(fn (Quotation $record): string => $record->approvalStatusLabel()),
                        TextEntry::make('pricing_policy')
                            ->label(__('Pricing policy'))
                            ->badge()
                            ->color(fn (Quotation $record): string => $record->pricingPolicyBadgeColor())
                            ->state(fn (Quotation $record): string => $record->pricingPolicyLabel()),
                        TextEntry::make('valid_until')
                            ->label(__('Valid until'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->iconColor('gray')
                            ->formatStateUsing(fn ($state): ?string => $state?->format(VietnamesePresentation::DATE_FORMAT))
                            ->placeholder('—'),
                        TextEntry::make('approved_at')
                            ->label(__('Approved at'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->iconColor('success')
                            ->dateTime(VietnamesePresentation::DATETIME_FORMAT)
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make(__('Uploaded files'))
                    ->description(__('Original files from the ingestion batch (same order as upload).'))
                    ->schema([
                        RepeatableEntry::make('ingestionBatch.files')
                            ->label('')
                            ->contained(false)
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label(__('File'))
                                    ->formatStateUsing(function (mixed $state, IngestionFile $record): HtmlString {
                                        $name = e((string) $state);
                                        $inline = e(route('ingestion.files.inline', $record));
                                        $download = e(route('ingestion.files.download', $record));
                                        $dl = e(__('Download'));

                                        return new HtmlString(
                                            '<a class="text-primary-600 underline decoration-primary-400/50 hover:decoration-primary-600 dark:text-primary-400" href="'.$inline.'">'.$name.'</a>'
                                            .' <span class="text-gray-400">·</span> '
                                            .'<a class="text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100" href="'.$download.'">'.$dl.'</a>'
                                        );
                                    })
                                    ->html(),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn (Quotation $record): bool => ($record->ingestionBatch !== null) && (($record->ingestionBatch->files?->count() ?? 0) > 0))
                    ->collapsed(),
            ]);
    }
}
