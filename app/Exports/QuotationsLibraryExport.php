<?php

namespace App\Exports;

use App\Models\Quotation;
use App\Support\Exports\VietnameseExcelHeaders;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class QuotationsLibraryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
        return VietnameseExcelHeaders::quotationLibrary();
    }

    /**
     * @param  Quotation  $row
     * @return list<int|string|null>
     */
    public function map($row): array
    {
        return [
            $row->supplier_name,
            $row->supplier_quote_number,
            $row->quote_date?->format('Y-m-d'),
            $row->total_amount !== null ? (string) $row->total_amount : '',
            $row->pricingPolicyLabel(),
            $row->valid_until?->format('Y-m-d'),
            $row->approved_at?->toIso8601String(),
            $row->approved_by,
        ];
    }
}
