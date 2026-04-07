<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Widgets\Reporting\ApprovedQuotationsWithoutPurchaseOrderWidget;
use App\Filament\Widgets\Reporting\ProductLastPurchaseFromPoWidget;
use App\Filament\Widgets\Reporting\PurchaseOrdersDueSoonWidget;
use App\Filament\Widgets\Reporting\QuotationsValidityAlertsWidget;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Quotations\QuotationResource;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;

class ProcurementReportingDashboard extends BaseDashboard
{
    use HasQuoteHubNavigationGroup;

    protected static string $routePath = 'procurement-reporting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return QuotationResource::canViewAny() && PurchaseOrderResource::canViewAny();
    }

    public static function getNavigationLabel(): string
    {
        return __('Procurement reports');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Procurement reports');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Procurement reports');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Purchase order due dates, quotation validity, coverage gaps, and last purchase prices by product.');
    }

    /**
     * @return int | array<string, int | null>
     */
    public function getColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            PurchaseOrdersDueSoonWidget::class,
            QuotationsValidityAlertsWidget::class,
            ApprovedQuotationsWithoutPurchaseOrderWidget::class,
            ProductLastPurchaseFromPoWidget::class,
        ];
    }
}
