<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\QuotationItem;
use App\Services\Catalog\ProductCreatePrefillFromQuotationLine;
use App\Services\Quotation\PriceHistoryQuery;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Suggestions from quotations'))
                    ->description(__('Approved quotations only — same visibility rules as price history.'))
                    ->schema([
                        FormSelect::make('prefill_quotation_line_id')
                            ->label(__('Prefill from approved line'))
                            ->helperText(__('Search by supplier, line text, model, or brand. Picking a row fills the fields below; adjust anything before saving.'))
                            ->native(false)
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->dehydrated(false)
                            ->getSearchResultsUsing(function (string $search): array {
                                $search = trim($search);
                                if ($search === '') {
                                    return [];
                                }

                                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                                return PriceHistoryQuery::make()
                                    ->with(['quotation'])
                                    ->where(function (Builder $q) use ($escaped): void {
                                        $q->where('quotation_items.raw_name', 'like', '%'.$escaped.'%')
                                            ->orWhere('quotation_items.raw_model', 'like', '%'.$escaped.'%')
                                            ->orWhere('quotation_items.brand', 'like', '%'.$escaped.'%')
                                            ->orWhere('quotations.supplier_name', 'like', '%'.$escaped.'%');
                                    })
                                    ->orderByDesc('quotations.approved_at')
                                    ->orderByDesc('quotation_items.id')
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (QuotationItem $item): array => [
                                        (string) $item->getKey() => ProductCreatePrefillFromQuotationLine::formatLineLabel($item),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (blank($value)) {
                                    return null;
                                }
                                $item = QuotationItem::query()->with('quotation')->find($value);

                                return $item ? ProductCreatePrefillFromQuotationLine::formatLineLabel($item) : null;
                            })
                            ->afterStateUpdated(function ($state, Set $set, FormSelect $component): void {
                                if ($state === null || $state === '') {
                                    return;
                                }
                                ProductCreatePrefillFromQuotationLine::apply(
                                    $set,
                                    (int) $state,
                                    $component->getModelRootContainer(),
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('create')
                    ->columnSpanFull(),
                Section::make(__('Product'))
                    ->schema([
                        FormSelect::make('brand_id')
                            ->label(__('Brand'))
                            ->relationship(
                                name: 'brand',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable(['name', 'code'])
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Brand name'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionModalHeading(__('New brand'))
                            ->createOptionUsing(function (array $data): int {
                                $name = trim((string) ($data['name'] ?? ''));
                                $brand = Brand::query()->create([
                                    'supplier_id' => null,
                                    'name' => $name,
                                    'slug' => self::uniqueSlugForModel($name, 'brand', Brand::class),
                                    'is_active' => true,
                                ]);

                                return (int) $brand->getKey();
                            }),
                        FormSelect::make('product_category_id')
                            ->label(__('Category'))
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('Category name'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionModalHeading(__('New category'))
                            ->createOptionUsing(function (array $data): int {
                                $name = trim((string) ($data['name'] ?? ''));
                                $category = ProductCategory::query()->create([
                                    'name' => $name,
                                    'slug' => self::uniqueSlugForModel($name, 'category', ProductCategory::class),
                                    'is_active' => true,
                                ]);

                                return (int) $category->getKey();
                            }),
                        TextInput::make('sku')
                            ->label(__('SKU'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label(__('Product name'))
                            ->required()
                            ->maxLength(512)
                            ->live(onBlur: true),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('Leave empty to generate from the product name when saving.')),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('specs_text')
                            ->label(__('Technical specifications'))
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('barcode')
                            ->label(__('Barcode'))
                            ->maxLength(64),
                        TextInput::make('unit_of_measure')
                            ->label(__('Unit of measure'))
                            ->maxLength(32)
                            ->placeholder(__('e.g. cái, hộp')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @param  class-string<Brand|ProductCategory>  $modelClass
     */
    private static function uniqueSlugForModel(string $name, string $fallbackPrefix, string $modelClass): string
    {
        $base = Str::slug($name) ?: $fallbackPrefix;
        $slug = $base;
        $n = 0;
        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
