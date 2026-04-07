<?php

namespace App\Exports;

use App\Models\QuotationItem;
use App\Support\Exports\VietnameseExcelHeaders;
use App\Support\Quotation\QuotationLinePresentation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class PriceHistoryLinesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query->clone();
    }

    public function headings(): array
    {
        return VietnameseExcelHeaders::priceHistoryLines();
    }

    /**
     * @param  QuotationItem  $row
     * @return list<int|string|null>
     */
    public function map($row): array
    {
        $quotation = $row->quotation;
        $product = $row->mappedProduct;
        $totalIncl = QuotationLinePresentation::lineTotalIncludingVat($row->line_total, $row->vat_percent);

        return [
            $row->getAttribute('price_history_group_key'),
            $row->quotation_id,
            $quotation?->supplier_name,
            $quotation?->supplier_quote_number,
            $quotation?->quote_date?->format('Y-m-d'),
            $row->raw_name,
            $row->raw_model,
            $row->brand,
            $row->quantity !== null ? (string) $row->quantity : '',
            $row->unit_price !== null ? (string) $row->unit_price : '',
            $row->vat_percent !== null ? (string) $row->vat_percent : '',
            $totalIncl !== null ? (string) $totalIncl : '',
            $quotation?->approved_at?->toIso8601String(),
            $product?->id,
            $product?->name,
            $product?->sku,
        ];
    }
}
