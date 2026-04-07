<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Support\Locale\VietnamesePresentation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Purchase order'))
                    ->schema([
                        TextInput::make('po_number')
                            ->label(__('PO number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Select::make('supplier_id')
                            ->label(__('Supplier'))
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('quotation_id')
                            ->label(__('Source quotation'))
                            ->relationship(
                                name: 'quotation',
                                titleAttribute: 'supplier_name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->whereNotNull('approved_at')
                                    ->orderByDesc('id'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Quotation $record): string => '#'.$record->getKey().' — '.mb_substr((string) $record->supplier_name, 0, 80))
                            ->searchable(['supplier_name', 'supplier_quote_number'])
                            ->preload()
                            ->nullable()
                            ->native(false),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(PurchaseOrder::statusOptions())
                            ->required()
                            ->default(PurchaseOrder::STATUS_DRAFT)
                            ->native(false),
                        DatePicker::make('order_date')
                            ->label(__('Order date'))
                            ->required()
                            ->native(false)
                            ->default(now())
                            ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                        DatePicker::make('expected_delivery_date')
                            ->label(__('Expected delivery'))
                            ->native(false)
                            ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                        TextInput::make('currency')
                            ->label(__('Currency'))
                            ->default('VND')
                            ->maxLength(8)
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
