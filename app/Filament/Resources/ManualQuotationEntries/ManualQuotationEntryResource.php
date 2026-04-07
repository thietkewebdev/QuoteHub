<?php

namespace App\Filament\Resources\ManualQuotationEntries;

use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\ManualQuotationEntries\Pages\EditManualQuotationEntry;
use App\Filament\Resources\ManualQuotationEntries\Pages\ListManualQuotationEntries;
use App\Filament\Resources\ManualQuotationEntries\Schemas\ManualQuotationEntryForm;
use App\Filament\Resources\ManualQuotationEntries\Tables\ManualQuotationEntriesTable;
use App\Models\QuotationReviewDraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualQuotationEntryResource extends Resource
{
    use HasQuoteHubNavigationGroup;

    protected static ?string $model = QuotationReviewDraft::class;

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function getNavigationLabel(): string
    {
        return __('Manual quotation entry');
    }

    public static function getModelLabel(): string
    {
        return __('Manual quotation draft');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Manual quotation drafts');
    }

    public static function form(Schema $schema): Schema
    {
        return ManualQuotationEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManualQuotationEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualQuotationEntries::route('/'),
            'edit' => EditManualQuotationEntry::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->manualEntryDrafts();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof QuotationReviewDraft) {
            return false;
        }

        return $record->ingestion_batch_id === null
            && $record->approved_quotation_id === null;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }
}
