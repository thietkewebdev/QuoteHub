<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Support\Locale\VietnamesePresentation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Line items');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('product_id')
                    ->label(__('Catalog product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->native(false)
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->label(__('Description'))
                    ->required()
                    ->maxLength(1024)
                    ->columnSpanFull(),
                TextInput::make('unit')
                    ->label(__('Unit'))
                    ->maxLength(64),
                TextInput::make('quantity')
                    ->label(__('Quantity'))
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->step(0.0001),
                TextInput::make('unit_price')
                    ->label(__('Unit price'))
                    ->required()
                    ->numeric()
                    ->suffix('đ')
                    ->step(0.0001),
                TextInput::make('vat_percent')
                    ->label(__('VAT %'))
                    ->numeric()
                    ->step(0.0001),
                TextInput::make('line_total')
                    ->label(__('Line subtotal (excl. VAT)'))
                    ->numeric()
                    ->suffix('đ')
                    ->step(0.0001)
                    ->helperText(__('Leave blank to use quantity × unit price.')),
                Textarea::make('notes')
                    ->label(__('Line notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->headerActions([
                CreateAction::make(),
            ])
            ->columns([
                TextColumn::make('line_no')
                    ->label(__('Line'))
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->wrap(),
                TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label(__('Qty'))
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
                TextColumn::make('line_total')
                    ->label(__('Line total'))
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn ($state): ?string => VietnamesePresentation::vnd($state)),
            ])
            ->defaultSort('line_no')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
