<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class QuotationComparePage extends Page
{
    use HasQuoteHubNavigationGroup;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * @var list<int>
     */
    public array $loadedComparisonIds = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return QuotationResource::canViewAny();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('quotation_ids')
                    ->label(__('Quotations to compare'))
                    ->helperText(__('Pick 2–3 quotes (same supplier or overlapping catalog products) to scan prices side by side.'))
                    ->multiple()
                    ->required()
                    ->minItems(2)
                    ->maxItems(3)
                    ->searchable()
                    ->getSearchResultsUsing(
                        function (string $search): array {
                            $term = trim($search);
                            if ($term === '') {
                                return [];
                            }

                            return Quotation::query()
                                ->where(function ($q) use ($term): void {
                                    $q->where('supplier_name', 'like', '%'.$term.'%')
                                        ->orWhere('supplier_quote_number', 'like', '%'.$term.'%');
                                    if (ctype_digit($term)) {
                                        $q->orWhere('id', (int) $term);
                                    }
                                })
                                ->orderByDesc('id')
                                ->limit(40)
                                ->get()
                                ->mapWithKeys(fn (Quotation $q): array => [
                                    $q->id => '#'.$q->id.' — '.mb_substr((string) $q->supplier_name, 0, 60)
                                        .($q->quote_date ? ' · '.$q->quote_date->format('Y-m-d') : ''),
                                ])
                                ->all();
                        },
                    )
                    ->getOptionLabelsUsing(function (array $values): array {
                        if ($values === []) {
                            return [];
                        }

                        return Quotation::query()
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Quotation $q): array => [
                                $q->id => '#'.$q->id.' — '.mb_substr((string) $q->supplier_name, 0, 80),
                            ])
                            ->all();
                    }),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('quotation-compare-form')
                    ->livewireSubmitHandler('runCompare')
                    ->footer([
                        Actions::make([
                            Action::make('compare')
                                ->label(__('Compare'))
                                ->submit('runCompare'),
                        ]),
                    ]),
                SchemaView::make('filament.pages.partials.quotation-compare-results')
                    ->viewData(fn (): array => [
                        'quotations' => $this->getComparisonQuotations(),
                    ]),
            ]);
    }

    public function runCompare(): void
    {
        $data = $this->form->getState();
        /** @var list<int|string> $rawIds */
        $rawIds = array_values($data['quotation_ids'] ?? []);
        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds))));

        if (count($ids) < 2 || count($ids) > 3) {
            Notification::make()
                ->danger()
                ->title(__('Select between 2 and 3 quotations.'))
                ->send();

            return;
        }

        $found = Quotation::query()->whereIn('id', $ids)->pluck('id')->all();
        if (count($found) !== count($ids)) {
            Notification::make()
                ->danger()
                ->title(__('One or more quotations were not found.'))
                ->send();

            return;
        }

        $this->loadedComparisonIds = $ids;

        $quotes = Quotation::query()->whereIn('id', $ids)->get();
        $distinctSuppliers = $quotes->pluck('supplier_name')->filter()->unique()->count();
        if ($distinctSuppliers > 1) {
            Notification::make()
                ->warning()
                ->title(__('Different supplier names'))
                ->body(__('Totals and terms may not be comparable; use this view for a quick scan only.'))
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title(__('Comparison loaded'))
                ->send();
        }
    }

    /**
     * @return Collection<int, Quotation>
     */
    protected function getComparisonQuotations(): Collection
    {
        if ($this->loadedComparisonIds === []) {
            return collect();
        }

        $order = array_flip($this->loadedComparisonIds);

        return Quotation::query()
            ->whereIn('id', $this->loadedComparisonIds)
            ->with(['items.mappedProduct', 'supplier'])
            ->get()
            ->sortBy(fn (Quotation $q): int => $order[$q->id] ?? 999);
    }

    public static function getNavigationLabel(): string
    {
        return __('Compare quotations');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Compare quotations');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Compare quotations');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Select two or three quotations to compare line totals and unit prices side by side.');
    }
}
