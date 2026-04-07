<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Exports\ApprovedQuotationItemsExport;
use App\Exports\QuotationsLibraryExport;
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
            Action::make('exportQuotationsLibraryExcel')
                ->label(__('Export list (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalDescription(__('Exports the same rows as the table after active filters and search.'))
                ->action(function (): BinaryFileResponse {
                    return Excel::download(
                        new QuotationsLibraryExport($this->getTableQueryForExport()->clone()),
                        'quotations-library-'.now()->format('Ymd-His').'.xlsx',
                    );
                })
                ->visible(fn (): bool => QuotationResource::canViewAny()),
            Action::make('exportApprovedQuotationItemsExcel')
                ->label(__('Export approved lines (Excel)'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->modalDescription(__('Uses the filtered quotation list: only lines whose quotation is approved are included.'))
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
