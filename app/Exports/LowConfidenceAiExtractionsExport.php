<?php

namespace App\Exports;

use App\Models\AiExtraction;
use App\Support\Exports\VietnameseExcelHeaders;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class LowConfidenceAiExtractionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query->clone()->with(['ingestionBatch.supplier']);
    }

    public function headings(): array
    {
        return VietnameseExcelHeaders::lowConfidenceAiExtractions();
    }

    /**
     * @param  AiExtraction  $row
     * @return list<int|string|null>
     */
    public function map($row): array
    {
        $batch = $row->ingestionBatch;

        return [
            $row->id,
            $row->ingestion_batch_id,
            $batch?->supplier?->name,
            $batch?->received_at?->format('Y-m-d H:i:s'),
            $batch?->status,
            $row->confidence_overall !== null ? (string) $row->confidence_overall : '',
            $row->model_name,
        ];
    }
}
