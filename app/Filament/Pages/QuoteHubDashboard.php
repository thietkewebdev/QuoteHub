<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasQuoteHubNavigationGroup;
use App\Filament\Widgets\Operations\ProductDashboardPriceSearchWidget;
use App\Filament\Widgets\Operations\SupplierDashboardSearchWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;

class QuoteHubDashboard extends BaseDashboard
{
    use HasQuoteHubNavigationGroup;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Operations dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Operations dashboard');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Operations dashboard');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Search catalog products for quick prices, or find suppliers from your catalog.');
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
            ProductDashboardPriceSearchWidget::class,
            SupplierDashboardSearchWidget::class,
        ];
    }
}
