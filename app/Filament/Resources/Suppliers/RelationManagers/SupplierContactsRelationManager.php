<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class SupplierContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    /**
     * Filament defaults relation managers on {@see ViewRecord} to read-only — hide Create / row actions.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        return Response::allow();
    }

    protected function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::allow();
    }

    protected function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::allow();
    }

    protected function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::allow();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Contact people');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Contact name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('job_title')
                    ->label(__('Job title'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(32),
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_primary')
                    ->label(__('Primary contact'))
                    ->helperText(__('Only one primary contact per supplier; saving clears the flag on others.')),
                TextInput::make('sort_order')
                    ->label(__('Sort order'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('Lower numbers appear first.')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add contact')),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('job_title')
                    ->label(__('Job title'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('—')
                    ->copyable(),
                IconColumn::make('is_primary')
                    ->label(__('Primary'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 25, 50]);
    }
}
