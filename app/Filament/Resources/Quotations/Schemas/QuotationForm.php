<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\Quotation;
use App\Support\Locale\VietnamesePresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('pricing_policy')
                    ->label(__('Pricing policy'))
                    ->options(Quotation::pricingPolicyOptions())
                    ->default(Quotation::PRICING_POLICY_STANDARD)
                    ->native(false)
                    ->hidden()
                    ->dehydrated(),
                DatePicker::make('valid_until')
                    ->label(__('Valid until'))
                    ->native(false)
                    ->displayFormat(VietnamesePresentation::DATE_FORMAT)
                    ->hidden()
                    ->dehydrated(),
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
