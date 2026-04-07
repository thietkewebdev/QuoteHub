<?php

namespace App\Filament\Actions;

use App\Actions\Quotation\SetQuotationItemProductMappingAction;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\Quotation\ProductMappingSuggestionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

final class MapQuotationItemToProductAction
{
    public static function make(): Action
    {
        return Action::make('mapQuotationItemToProduct')
            ->label(__('Map to product'))
            ->icon(Heroicon::OutlinedLink)
            ->modalHeading(__('Map line to canonical product'))
            ->modalDescription(__('Raw extracted fields are never changed. Each save is recorded in the mapping audit log.'))
            ->fillForm(fn (QuotationItem $record): array => [
                'product_id' => $record->mapped_product_id,
            ])
            ->schema(function (QuotationItem $record): array {
                $suggestions = app(ProductMappingSuggestionService::class)->suggest($record);

                $hint = $suggestions->isEmpty()
                    ? e(__('No automatic suggestions. Search for a product below.'))
                    : $suggestions->map(fn ($s) => '• '.e($s->dropdownLabel()))->implode('<br>');

                return [
                    Placeholder::make('suggestions_hint')
                        ->label(__('Suggested matches (reference only)'))
                        ->content(new HtmlString($hint))
                        ->columnSpanFull(),
                    Select::make('product_id')
                        ->label(__('Canonical product'))
                        ->placeholder(__('None — clear mapping'))
                        ->native(false)
                        ->searchable()
                        ->nullable()
                        ->options(function () use ($record, $suggestions): array {
                            $map = $suggestions->mapWithKeys(
                                fn ($s) => [$s->productId => $s->dropdownLabel()]
                            )->all();

                            if ($record->mapped_product_id) {
                                $current = Product::query()->whereKey($record->mapped_product_id)->first();
                                if ($current !== null && ! array_key_exists($current->id, $map)) {
                                    $sku = $current->sku !== null && $current->sku !== '' ? ' ('.$current->sku.')' : '';
                                    $map[$current->id] = $current->name.$sku;
                                }
                            }

                            return $map;
                        })
                        ->getSearchResultsUsing(function (string $search): array {
                            $search = trim($search);
                            if ($search === '') {
                                return [];
                            }

                            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                            return Product::query()
                                ->where('is_active', true)
                                ->where(function ($q) use ($escaped): void {
                                    $q->where('name', 'like', '%'.$escaped.'%')
                                        ->orWhere('sku', 'like', '%'.$escaped.'%');
                                })
                                ->orderBy('name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function (Product $p): array {
                                    $sku = $p->sku !== null && $p->sku !== '' ? ' ('.$p->sku.')' : '';

                                    return [$p->id => $p->name.$sku];
                                })
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $p = Product::query()->whereKey($value)->first();
                            if ($p === null) {
                                return null;
                            }
                            $sku = $p->sku !== null && $p->sku !== '' ? ' ('.$p->sku.')' : '';

                            return $p->name.$sku;
                        }),
                ];
            })
            ->action(function (array $data, QuotationItem $record): void {
                try {
                    $user = auth()->user();
                    if (! $user instanceof User) {
                        Notification::make()
                            ->danger()
                            ->title(__('You must be signed in.'))
                            ->send();

                        return;
                    }

                    $raw = $data['product_id'] ?? null;
                    $productId = $raw === null || $raw === '' ? null : (int) $raw;

                    app(SetQuotationItemProductMappingAction::class)->execute(
                        $record,
                        $user,
                        $productId
                    );

                    Notification::make()
                        ->success()
                        ->title(__('Mapping saved'))
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title($e->getMessage())
                        ->send();
                }
            });
    }
}
