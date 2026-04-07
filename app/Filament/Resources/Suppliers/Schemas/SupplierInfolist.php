<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Supplier'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Supplier name'))
                            ->weight(FontWeight::SemiBold)
                            ->columnSpanFull(),
                        TextEntry::make('phone')
                            ->label(__('Phone'))
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('email')
                            ->label(__('Email'))
                            ->placeholder('—')
                            ->copyable(),
                    ]),
            ]);
    }
}
