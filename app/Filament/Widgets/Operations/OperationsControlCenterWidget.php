<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Operations;

use App\Filament\Pages\QuotationComparePage;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
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
        $canProducts = ProductResource::canViewAny();
        $canPurchaseOrders = PurchaseOrderResource::canViewAny();

        $quotationsCount = $canQuotes
            ? (string) number_format(Quotation::query()->count())
            : '—';

        $suppliersCount = $canSuppliers
            ? (string) number_format(Supplier::query()->where('is_active', true)->count())
            : '—';

        $productsCount = $canProducts
            ? (string) number_format(Product::query()->where('is_active', true)->count())
            : '—';

        $poTotalValue = '—';
        if ($canPurchaseOrders) {
            $sum = (float) (PurchaseOrder::query()
                ->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)
                ->sum('total_amount') ?? 0);
            $poTotalValue = VietnamesePresentation::vnd($sum) ?? '—';
        }

        return [
            [
                'key' => 'quotations',
                'label' => __('Total quotations'),
                'value' => $quotationsCount,
            ],
            [
                'key' => 'suppliers',
                'label' => __('Total suppliers'),
                'value' => $suppliersCount,
            ],
            [
                'key' => 'products',
                'label' => __('Total products'),
                'value' => $productsCount,
            ],
            [
                'key' => 'po_value',
                'label' => __('Total PO value'),
                'value' => $poTotalValue,
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
                'href' => QuotationResource::getUrl('index'),
                'icon' => 'plus',
                'visible' => QuotationResource::canViewAny(),
            ],
            [
                'label' => __('Compare quotations'),
                'href' => QuotationComparePage::getUrl(),
                'icon' => 'scale',
                'visible' => QuotationComparePage::canAccess(),
            ],
        ];
    }
}
