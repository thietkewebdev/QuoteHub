<?php

namespace App\Filament\Resources\IngestionBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->dateTime(),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('file_count')
                            ->label(__('File count'))
                            ->numeric(),
                        TextEntry::make('overall_confidence')
                            ->label(__('Overall confidence'))
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state) => $state === null ? null : (string) $state),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make(__('Audit'))
                    ->collapsed()
                    ->schema([
                        TextEntry::make('uploader.name')
                            ->label(__('Uploaded by'))
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label(__('Last updated'))
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
