<?php

namespace App\Filament\Forms;

use App\Models\IngestionBatch;
use App\Models\Supplier;
use App\Services\Supplier\SupplierMatchingService;
use App\Services\Supplier\SupplierRegistryService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * Reusable supplier picker: search (name, code, normalized key), ranked matches, inline create.
 */
final class SupplierSelect
{
    /**
     * For Eloquent forms where the record defines a {@see IngestionBatch::supplier()} BelongsTo.
     */
    public static function make(string $name = 'supplier_id'): Select
    {
        $matching = app(SupplierMatchingService::class);

        return Select::make($name)
            ->label(__('Supplier'))
            ->relationship(
                'supplier',
                'name',
                fn (Builder $query, ?string $search) => $matching->applySearchRanking($query, $search),
            )
            ->searchable(['name', 'code', 'normalized_name'])
            ->forceSearchCaseInsensitive()
            ->preload()
            ->nullable()
            ->native(false)
            ->getOptionLabelFromRecordUsing(fn (Supplier $record): string => self::formatSupplierLabel($record))
            ->createOptionForm(self::createOptionFormSchema())
            ->createOptionModalHeading(__('New supplier'))
            ->createOptionUsing(self::createOptionUsingCallback())
            ->helperText(__('Search by supplier name (Vietnamese OK). Best matches appear first. Use + to add a new supplier if it is not listed.'))
            ->rules(['nullable', new Exists('suppliers', 'id')]);
    }

    /**
     * For JSON / nested payloads (e.g. manual quotation drafts) without a BelongsTo on the form model.
     */
    public static function makeForPayload(string $name = 'supplier_id'): Select
    {
        $matching = app(SupplierMatchingService::class);

        return Select::make($name)
            ->label(__('Supplier catalog (optional)'))
            ->searchable()
            ->preload()
            ->nullable()
            ->native(false)
            ->options(fn (): array => self::preloadOptions())
            ->getSearchResultsUsing(function (string $search) use ($matching): array {
                $query = Supplier::query();
                $connection = $query->getConnection();
                $term = generate_search_term_expression($search, true, $connection);

                $query->where(function (Builder $inner) use ($term, $connection): void {
                    $isFirst = true;
                    foreach (['name', 'code', 'normalized_name'] as $column) {
                        $clause = $isFirst ? 'where' : 'orWhere';
                        $inner->{$clause}(
                            generate_search_column_expression($column, true, $connection),
                            'like',
                            "%{$term}%",
                        );
                        $isFirst = false;
                    }
                });

                $matching->applySearchRanking($query, $search);

                return $query
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Supplier $s): array => [$s->getKey() => self::formatSupplierLabel($s)])
                    ->all();
            })
            ->getOptionLabelUsing(function ($value): ?string {
                if (blank($value)) {
                    return null;
                }
                $supplier = Supplier::query()->find($value);

                return $supplier ? self::formatSupplierLabel($supplier) : null;
            })
            ->createOptionForm(self::createOptionFormSchema())
            ->createOptionModalHeading(__('New supplier'))
            ->createOptionUsing(self::createOptionUsingCallback())
            ->helperText(__('Search by supplier name (Vietnamese OK). Best matches appear first. Use + to add a new supplier if it is not listed.'))
            ->rules(['nullable', new Exists('suppliers', 'id')]);
    }

    /**
     * @return array<TextInput>
     */
    private static function createOptionFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Supplier name'))
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label(__('Supplier code (optional)'))
                ->maxLength(64),
        ];
    }

    /**
     * @return Closure(array): int
     */
    private static function createOptionUsingCallback(): Closure
    {
        return function (array $data): int {
            $registry = app(SupplierRegistryService::class);
            $supplier = $registry->findOrCreateByDisplayName(
                (string) ($data['name'] ?? ''),
                isset($data['code']) ? (string) $data['code'] : null,
            );

            return (int) $supplier->getKey();
        };
    }

    /**
     * @return array<int|string, string>
     */
    private static function preloadOptions(): array
    {
        return Supplier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Supplier $s): array => [$s->getKey() => self::formatSupplierLabel($s)])
            ->all();
    }

    private static function formatSupplierLabel(Supplier $record): string
    {
        $label = (string) $record->name;
        if (filled($record->code)) {
            $label .= ' ('.$record->code.')';
        }
        if ($record->is_active === false) {
            $label .= ' — '.__('inactive');
        }

        return $label;
    }
}
