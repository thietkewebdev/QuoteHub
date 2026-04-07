<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->heading(__('Product overview'))
                    ->description(__('Name, brand, and description — the essentials your team sees across quotes and history.'))
                    ->icon(Heroicon::OutlinedCube)
                    ->iconColor('primary')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->icon(Heroicon::OutlinedCube)
                                    ->iconColor('gray')
                                    ->columnSpanFull(),
                                TextEntry::make('brand.name')
                                    ->label(__('Brand'))
                                    ->placeholder('—')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->iconColor('gray')
                                    ->badge(fn (?string $state): bool => filled($state))
                                    ->color('primary')
                                    ->columnSpanFull(),
                                TextEntry::make('description')
                                    ->label(__('Description'))
                                    ->placeholder('—')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->iconColor('gray')
                                    ->prose()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
