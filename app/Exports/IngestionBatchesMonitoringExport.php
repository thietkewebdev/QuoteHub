<?php

namespace App\Exports;

use App\Models\IngestionBatch;
use App\Support\Exports\VietnameseExcelHeaders;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class IngestionBatchesMonitoringExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query->clone()->with(['supplier']);
    }

    public function headings(): array
    {
        return VietnameseExcelHeaders::ingestionBatchesMonitoring();
    }

    /**
     * @param  IngestionBatch  $row
     * @return list<int|string|null>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->supplier?->name,
            $row->received_at?->format('Y-m-d H:i:s'),
            $row->status,
            $row->file_count,
        ];
    }
}
