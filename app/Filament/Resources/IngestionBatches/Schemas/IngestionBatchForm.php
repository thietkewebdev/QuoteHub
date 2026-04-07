<?php

namespace App\Filament\Resources\IngestionBatches\Schemas;

use App\Filament\Forms\SupplierSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\In;

class IngestionBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        $channels = array_keys(config('ingestion.source_channels', []));
        $maxKb = (int) config('ingestion.max_file_size_kb', 20_480);
        $mimes = config('ingestion.allowed_mime_types', []);
        $staging = (string) config('ingestion.staging_directory', 'tmp/ingestion_uploads');
        $disk = (string) config('ingestion.disk', 'local');

        return $schema
            ->components([
                Section::make(__('Batch'))
                    ->schema([
                        Select::make('source_channel')
                            ->label(__('Source channel'))
                            ->options(config('ingestion.source_channels', []))
                            ->required()
                            ->native(false)
                            ->rules([new In($channels)]),
                        SupplierSelect::make(),
                        DateTimePicker::make('received_at')
                            ->label(__('Received at'))
                            ->required()
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->default(now()),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('Files'))
                    ->description(__('Images, PDF, XLSX, or DOCX. Max :max KB per file.', ['max' => $maxKb]))
                    ->schema([
                        FileUpload::make('uploads')
                            ->label(__('Documents'))
                            ->disk($disk)
                            ->directory($staging)
                            ->visibility('private')
                            ->multiple()
                            ->minFiles(1)
                            ->maxParallelUploads(3)
                            ->maxSize($maxKb)
                            ->acceptedFileTypes($mimes)
                            ->storeFileNamesIn('upload_original_names')
                            ->required()
                            ->hiddenOn('edit')
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('edit'),
            ]);
    }
}
