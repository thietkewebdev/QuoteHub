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
                        if (! $record->isRasterImage()) {
                            return new HtmlString('<span class="text-sm text-gray-400 dark:text-gray-500">—</span>');
                        }

                        $url = route('ingestion.files.inline', $record);

                        return new HtmlString(
                            '<img src="'.e($url).'" alt="" class="h-14 w-auto max-w-[7rem] rounded-md object-cover ring-1 ring-gray-950/5 dark:ring-white/10" loading="lazy" />'
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
