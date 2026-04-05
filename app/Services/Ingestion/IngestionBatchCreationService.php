<?php

namespace App\Services\Ingestion;

use App\Models\IngestionBatch;

class IngestionBatchCreationService
{
    /**
     * @param  array{source_channel: string, supplier_id?: int|null, received_at: mixed, notes?: string|null}  $payload
     */
    public function createPendingBatch(array $payload, ?int $uploadedByUserId): IngestionBatch
    {
        $payload = IngestionUploadValidator::validatedBatchPayload($payload);

        return IngestionBatch::query()->create([
            'source_channel' => $payload['source_channel'],
            'supplier_id' => $payload['supplier_id'] ?? null,
            'received_at' => $payload['received_at'],
            'uploaded_by' => $uploadedByUserId,
            'notes' => $payload['notes'] ?? null,
            'status' => 'pending',
            'file_count' => 0,
            'overall_confidence' => null,
        ]);
    }
}
