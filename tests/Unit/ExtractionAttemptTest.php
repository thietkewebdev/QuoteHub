<?php

namespace Tests\Unit;

use App\Models\ExtractionAttempt;
use App\Models\IngestionBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractionAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_attempt_number_increments_per_batch(): void
    {
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'ocr_done',
        ]);

        $this->assertSame(1, ExtractionAttempt::nextAttemptNumber($batch->id));

        ExtractionAttempt::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'attempt_number' => 1,
            'is_latest' => true,
            'model_name' => 'm',
            'prompt_version' => 'v',
            'result_json' => [],
            'confidence_overall' => 0.5,
        ]);

        $this->assertSame(2, ExtractionAttempt::nextAttemptNumber($batch->id));
    }
}
