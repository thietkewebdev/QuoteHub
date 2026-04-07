<?php

namespace App\Filament\Resources\IngestionBatches\RelationManagers;

use App\Models\IngestionFile;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\View\ComponentAttributeBag;

use function Filament\Support\generate_icon_html;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Uploaded files';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('preview')
                    ->label(__('Preview'))
                    ->html()
                    ->formatStateUsing(function ($state, IngestionFile $record): HtmlString {
                        if (! $record->isRasterImage() || blank($record->storage_path)) {
                            $icon = generate_icon_html(
                                Heroicon::OutlinedDocument,
                                attributes: new ComponentAttributeBag,
                            );

                            return new HtmlString(
                                '<div class="flex h-20 max-w-[10rem] items-center justify-start text-gray-400 dark:text-gray-500" role="img" aria-label="'.e(__('File')).'">'
                                .($icon?->toHtml() ?? '')
                                .'</div>'
                            );
                        }

                        // Private storage: img URL is the auth-only inline route; the controller resolves the bytes from storage_path on the server.
                        $url = route('ingestion.files.inline', $record);

                        $dimAttrs = '';
                        if (filled($record->width) && filled($record->height)) {
                            $dimAttrs = ' width="'.(int) $record->width.'" height="'.(int) $record->height.'"';
                        }

                        return new HtmlString(
                            '<div class="flex h-20 max-w-[12rem] items-center justify-start">'
                            .'<img src="'.e($url).'" alt=""'.$dimAttrs
                            .' class="max-h-20 w-auto max-w-full object-contain rounded-md ring-1 ring-gray-950/10 dark:ring-white/10" loading="lazy" decoding="async" />'
                            .'</div>'
                        );
                    }),
                TextColumn::make('page_order')
                    ->label(__('Order'))
                    ->sortable(),
                TextColumn::make('original_name')
                    ->label(__('Original name'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('mime_type')
                    ->label(__('MIME type'))
                    ->fontFamily(FontFamily::Mono)
                    ->size('sm')
                    ->wrap(),
                TextColumn::make('file_size')
                    ->label(__('Size'))
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Number::fileSize($state)),
                TextColumn::make('extension')
                    ->label(__('Ext'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('width')
                    ->label(__('W'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('height')
                    ->label(__('H'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('checksum_sha256')
                    ->label(__('SHA-256'))
                    ->fontFamily(FontFamily::Mono)
                    ->limit(14)
                    ->tooltip(fn (IngestionFile $record) => $record->checksum_sha256)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (IngestionFile $record): string => route('ingestion.files.inline', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (IngestionFile $record): bool => $record->supportsInlinePreview()),
                Action::make('download')
                    ->label(__('Download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (IngestionFile $record): string => route('ingestion.files.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('page_order')
            ->paginated([10, 25, 50]);
    }
}
