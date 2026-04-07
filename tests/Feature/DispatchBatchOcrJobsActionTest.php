<?php

namespace Tests\Feature;

use App\Actions\Ingestion\DispatchBatchOcrJobsAction;
use App\Models\IngestionBatch;
use App\Models\IngestionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchBatchOcrJobsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_then_queue_ai_reports_no_files_idle_reason(): void
    {
        Bus::fake();

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);

        $result = app(DispatchBatchOcrJobsAction::class)->executeThenQueueAi($batch);

        $this->assertSame(0, $result['ocr_jobs']);
        $this->assertFalse($result['ai_queued']);
        $this->assertSame('no_files', $result['idle_reason']);
    }

    public function test_execute_then_queue_ai_reports_no_ocr_eligible_files_when_only_spreadsheet(): void
    {
        Bus::fake();

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);

        IngestionFile::query()->create([
            'ingestion_batch_id' => $batch->id,
            'original_name' => 'sheet.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
            'storage_path' => 'ingestion/test.xlsx',
            'checksum_sha256' => str_repeat('a', 64),
            'page_order' => 0,
            'file_size' => 1,
        ]);

        $result = app(DispatchBatchOcrJobsAction::class)->executeThenQueueAi($batch);

        $this->assertSame(0, $result['ocr_jobs']);
        $this->assertSame('no_ocr_eligible_files', $result['idle_reason']);
    }

    public function test_execute_then_queue_ai_queues_jobs_for_pdf(): void
    {
        Bus::fake();

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);

        IngestionFile::query()->create([
            'ingestion_batch_id' => $batch->id,
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'storage_path' => 'ingestion/test.pdf',
            'checksum_sha256' => str_repeat('b', 64),
            'page_order' => 0,
            'file_size' => 1,
        ]);

        $result = app(DispatchBatchOcrJobsAction::class)->executeThenQueueAi($batch);

        $this->assertGreaterThan(0, $result['ocr_jobs']);
        $this->assertTrue($result['ai_queued']);
        $this->assertFalse($result['ai_immediate']);
        Bus::assertBatched(fn ($batch) => str_starts_with($batch->name, 'ingestion-ocr-'));
    }
}
