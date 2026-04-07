<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Tables\ProductMappedPriceLinesTable;
use App\Services\Quotation\ProductPriceHistoryQuery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MappedPriceLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'mappedQuotationItems';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Price history (approved lines)');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        // Use the same scoped query as ProductPriceHistoryQuery tests — only lines with
        // quotation_items.mapped_product_id = this product, approved quotes, batch rules.
        return ProductMappedPriceLinesTable::configure(
            $table->query(function (): Builder {
                return ProductPriceHistoryQuery::forProduct((int) $this->getOwnerRecord()->getKey())
                    ->with(['quotation', 'mappedProduct']);
            }),
        );
    }
}
