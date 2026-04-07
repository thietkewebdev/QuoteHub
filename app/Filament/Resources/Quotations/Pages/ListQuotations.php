<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Exports\ApprovedQuotationItemsExport;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\QuotationItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('exportApprovedQuotationItemsExcel')
                ->label(__('Export (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalDescription(__('Only lines from approved quotations are exported. The current table filters and search still limit which quotations are included.'))
                ->action(function (): BinaryFileResponse {
                    $quotationIdsQuery = $this->getTableQueryForExport()->clone()->select('quotations.id');

                    $itemsQuery = QuotationItem::query()
                        ->whereIn('quotation_id', $quotationIdsQuery)
                        ->whereHas('quotation', fn (Builder $q): Builder => $q->whereNotNull('approved_at'))
                        ->with(['quotation', 'mappedProduct'])
                        ->orderBy('quotation_id')
                        ->orderBy('line_no');

                    return Excel::download(
                        new ApprovedQuotationItemsExport($itemsQuery),
                        'quotation-items-approved-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => QuotationResource::canViewAny()),
        ];
    }
}
