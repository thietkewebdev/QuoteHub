<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\QuotationComparePage;
use App\Filament\Resources\IngestionBatches\IngestionBatchResource;
use App\Filament\Resources\ManualQuotationEntries\ManualQuotationEntryResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Support\Locale\VietnamesePresentation;
use Filament\Widgets\Widget;

/**
 * KPI strip + quick actions for the operations (Quote Hub) dashboard.
 */
final class OperationsControlCenterWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operations.control-center';

    public static function canView(): bool
    {
        return auth()->check();
    }

    protected function getViewData(): array
    {
        return [
            'kpis' => $this->kpiFigures(),
            'quickActions' => array_values(array_filter(
                $this->quickActions(),
                static fn (array $a): bool => $a['visible'],
            )),
        ];
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    private function kpiFigures(): array
    {
        $canQuotes = QuotationResource::canViewAny();
        $canSuppliers = SupplierResource::canViewAny();

        $quotationsCount = $canQuotes
            ? (string) number_format(Quotation::query()->count())
            : '—';

        $totalValue = '—';
        if ($canQuotes) {
            $sum = (float) (Quotation::query()
                ->whereNotNull('approved_at')
                ->where(function ($q): void {
                    $q->whereNull('pricing_policy')
                        ->orWhere('pricing_policy', '!=', Quotation::PRICING_POLICY_VOID);
                })
                ->sum('total_amount') ?? 0);
            $totalValue = VietnamesePresentation::vnd($sum) ?? '—';
        }

        $suppliersCount = $canSuppliers
            ? (string) number_format(Supplier::query()->where('is_active', true)->count())
            : '—';

        $pending = '—';
        if ($canQuotes) {
            $pending = (string) number_format(Quotation::query()
                ->whereNull('approved_at')
                ->where(function ($q): void {
                    $q->whereNull('pricing_policy')
                        ->orWhere('pricing_policy', '!=', Quotation::PRICING_POLICY_VOID);
                })
                ->count());
        }

        return [
            [
                'key' => 'quotations',
                'label' => __('Total quotations'),
                'value' => $quotationsCount,
            ],
            [
                'key' => 'value',
                'label' => __('Total value'),
                'value' => $totalValue,
            ],
            [
                'key' => 'suppliers',
                'label' => __('Total suppliers'),
                'value' => $suppliersCount,
            ],
            [
                'key' => 'pending',
                'label' => __('Pending approvals'),
                'value' => $pending,
            ],
        ];
    }

    /**
     * @return list<array{label: string, href: string, icon: string, visible: bool}>
     */
    private function quickActions(): array
    {
        return [
            [
                'label' => __('Create quotation'),
                'href' => ManualQuotationEntryResource::getUrl('index'),
                'icon' => 'plus',
                'visible' => ManualQuotationEntryResource::canViewAny(),
            ],
            [
                'label' => __('Compare quotations'),
                'href' => QuotationComparePage::getUrl(),
                'icon' => 'scale',
                'visible' => QuotationComparePage::canAccess(),
            ],
            [
                'label' => __('Import data'),
                'href' => IngestionBatchResource::getUrl('create'),
                'icon' => 'arrow-up-tray',
                'visible' => IngestionBatchResource::canCreate(),
            ],
        ];
    }
}
