<?php

namespace App\Filament\Resources\IngestionBatches;

use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\IngestionBatches\Pages\CreateIngestionBatch;
use App\Filament\Resources\IngestionBatches\Pages\EditIngestionBatch;
use App\Filament\Resources\IngestionBatches\Pages\ListIngestionBatches;
use App\Filament\Resources\IngestionBatches\Pages\ReviewIngestionQuotation;
use App\Filament\Resources\IngestionBatches\Pages\ViewIngestionBatch;
use App\Filament\Resources\IngestionBatches\Pages\ViewIngestionOcrCapture;
use App\Filament\Resources\IngestionBatches\RelationManagers\FilesRelationManager;
use App\Filament\Resources\IngestionBatches\Schemas\IngestionBatchForm;
use App\Filament\Resources\IngestionBatches\Schemas\IngestionBatchInfolist;
use App\Filament\Resources\IngestionBatches\Tables\IngestionBatchesTable;
use App\Models\IngestionBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IngestionBatchResource extends Resource
{
    use HasQuoteHubNavigationGroup;

    protected static ?string $model = IngestionBatch::class;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('Báo giá tự động');
    }

    public static function getModelLabel(): string
    {
        return __('Ingestion batch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ingestion batches');
    }

    public static function form(Schema $schema): Schema
    {
        return IngestionBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IngestionBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngestionBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIngestionBatches::route('/'),
            'create' => CreateIngestionBatch::route('/create'),
            'view' => ViewIngestionBatch::route('/{record}'),
            'ocrCapture' => ViewIngestionOcrCapture::route('/{record}/ocr-capture'),
            'reviewQuotation' => ReviewIngestionQuotation::route('/{record}/review-quotation'),
            'edit' => EditIngestionBatch::route('/{record}/edit'),
        ];
    }
}
