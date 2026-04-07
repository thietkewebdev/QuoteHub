<?php

namespace App\Exports;

use App\Models\QuotationItem;
use App\Support\Exports\VietnameseExcelHeaders;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class UnmappedQuotationLinesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query->clone()->with(['quotation.supplier', 'quotation.ingestionBatch']);
    }

    public function headings(): array
    {
        return VietnameseExcelHeaders::unmappedQuotationLines();
    }

    /**
     * @param  QuotationItem  $row
     * @return list<int|string|null>
     */
    public function map($row): array
    {
        $quotation = $row->quotation;

        return [
            $row->quotation_id,
            $quotation?->supplier_name,
            $quotation?->approved_at?->toIso8601String(),
            $row->raw_name,
            $row->raw_model,
            $row->brand,
            $row->quantity !== null ? (string) $row->quantity : '',
            $row->unit_price !== null ? (string) $row->unit_price : '',
            $row->vat_percent !== null ? (string) $row->vat_percent : '',
            $row->line_total !== null ? (string) $row->line_total : '',
        ];
    }
}
