<?php

namespace App\Filament\Concerns;

use UnitEnum;

trait HasQuoteHubNavigationGroup
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Quote Hub');
    }
}
