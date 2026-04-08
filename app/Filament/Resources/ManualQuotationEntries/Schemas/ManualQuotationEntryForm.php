<?php

namespace App\Filament\Resources\ManualQuotationEntries\Schemas;

use App\Filament\Forms\SupplierSelect;
use App\Models\Supplier;
use App\Support\Locale\VietnameseMoneyInput;
use App\Support\Locale\VietnamesePresentation;
use App\Support\Quotation\ManualQuotationLineVatUi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ClosureValidationRule;

class ManualQuotationEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Manual quotation'))
                    ->description(__('Entered directly — no ingestion batch or AI extraction. Save a draft, then approve to create the final quotation.'))
                    ->schema([
                        TextInput::make('supplier_name')
                            ->label(__('Supplier name'))
                            ->required()
                            ->maxLength(512)
                            ->helperText(__('Enter the supplier by hand, or pick from the catalog below to fill this field automatically.')),
                        SupplierSelect::makeForPayload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state === null || $state === '') {
                                    return;
                                }
                                $name = Supplier::query()->find((int) $state)?->name;
                                if (is_string($name) && $name !== '') {
                                    $set('supplier_name', $name);
                                }
                            }),
                        TextInput::make('supplier_quote_number')
                            ->label(__('Supplier quote number'))
                            ->maxLength(128),
                        DatePicker::make('quote_date')
                            ->label(__('Quote date'))
                            ->native(false)
                            ->displayFormat(VietnamesePresentation::DATE_FORMAT),
                        TextInput::make('contact_person')
                            ->label(__('Contact person'))
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('total_amount')
                            ->label(__('Total amount (VND)'))
                            ->helperText(__('Leave blank to use the sum of line totals when you save or approve.'))
                            ->suffix('đ')
                            ->formatStateUsing(fn ($state): ?string => VietnameseMoneyInput::formatForDisplay($state))
                            ->dehydrateStateUsing(fn ($state): ?float => VietnameseMoneyInput::parse($state))
                            ->rules(self::vnMoneyRules())
                            ->live(debounce: 250)
                            ->afterStateUpdated(function ($state, callable $set): void {
                                VietnameseMoneyInput::reformatLiveState($set, $state);
                            }),
                        Textarea::make('reviewer_notes')
                            ->label(__('Reviewer notes (internal)'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('Line items'))
                    ->schema([
                        Repeater::make('items')
                            ->label(__('Items'))
                            ->schema([
                                Hidden::make('mapped_product_id'),
                                TextInput::make('raw_name')
                                    ->label(__('Product name'))
                                    ->columnSpanFull(),
                                TextInput::make('raw_model')
                                    ->label(__('Model')),
                                TextInput::make('brand')
                                    ->label(__('Brand')),
                                TextInput::make('unit')
                                    ->label(__('Unit')),
                                TextInput::make('quantity')
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    }),
                                TextInput::make('unit_price')
                                    ->label(__('Unit price (excl. VAT)'))
                                    ->suffix('đ')
                                    ->formatStateUsing(fn ($state): ?string => VietnameseMoneyInput::formatForDisplay($state))
                                    ->dehydrateStateUsing(fn ($state): ?float => VietnameseMoneyInput::parse($state))
                                    ->rules(self::vnMoneyRules())
                                    ->live(debounce: 250)
                                    ->helperText(__('Before tax — used with quantity for subtotal excl. VAT.'))
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        VietnameseMoneyInput::reformatLiveState($set, $state);
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    }),
                                TextInput::make('line_total')
                                    ->label(__('Line subtotal (excl. VAT)'))
                                    ->suffix('đ')
                                    ->formatStateUsing(fn ($state): ?string => VietnameseMoneyInput::formatForDisplay($state))
                                    ->dehydrateStateUsing(fn ($state): ?float => VietnameseMoneyInput::parse($state))
                                    ->rules(self::vnMoneyRules())
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->helperText(__('Quantity × unit price (excl. VAT).')),
                                TextInput::make('vat_percent')
                                    ->label(__('VAT %'))
                                    ->numeric()
                                    ->step(0.0001)
                                    ->live(debounce: 250)
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    }),
                                TextInput::make('vat_amount_display')
                                    ->label(__('VAT amount'))
                                    ->suffix('đ')
                                    ->formatStateUsing(fn ($state): ?string => VietnameseMoneyInput::formatForDisplay($state))
                                    ->dehydrateStateUsing(fn ($state): ?float => VietnameseMoneyInput::parse($state))
                                    ->rules(self::vnMoneyRules())
                                    ->live(debounce: 250)
                                    ->dehydrated(false)
                                    ->helperText(__('Filled from VAT % (rounded to whole đồng); edit to match invoice rounding.'))
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        VietnameseMoneyInput::reformatLiveState($set, $state);
                                        ManualQuotationLineVatUi::applyManualVatAmount($set, $get);
                                    }),
                                TextInput::make('line_gross_display')
                                    ->label(__('Line total (incl. VAT)'))
                                    ->suffix('đ')
                                    ->formatStateUsing(fn ($state): ?string => VietnameseMoneyInput::formatForDisplay($state))
                                    ->dehydrateStateUsing(fn ($state): ?float => VietnameseMoneyInput::parse($state))
                                    ->rules(self::vnMoneyRules())
                                    ->live(debounce: 250)
                                    ->dehydrated(false)
                                    ->columnSpanFull()
                                    ->helperText(__('Optional: enter the invoice total with VAT; excl. subtotal and VAT are derived from VAT %.'))
                                    ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                                        ManualQuotationLineVatUi::sync($set, $get, subtotalFromQtyUnitPrice: true);
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        VietnameseMoneyInput::reformatLiveState($set, $state);
                                        ManualQuotationLineVatUi::applyInclusiveGross($set, $get);
                                    }),
                                Textarea::make('specs_text')
                                    ->label(__('Specs'))
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['raw_name'] ?? __('Line item'))
                            ->addActionLabel(__('Add line')),
                    ]),
            ]);
    }

    /**
     * @return list<ClosureValidationRule>
     */
    private static function vnMoneyRules(): array
    {
        return [
            new ClosureValidationRule(function (string $attribute, mixed $value, callable $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                if (VietnameseMoneyInput::parse($value) === null) {
                    $fail(__('Enter a valid amount (e.g. 1.080.000 or 1.080.000,5).'));
                }
            }),
        ];
    }
}
