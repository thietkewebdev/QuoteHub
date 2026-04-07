<?php

namespace App\Jobs\OCR;

use App\Models\IngestionFile;
use App\Models\OcrResult;
use App\Services\OCR\OcrExtractionException;
use App\Services\OCR\OcrExtractionService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunOcrForFileJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public IngestionFile $ingestionFile) {}

    public function handle(OcrExtractionService $ocrExtractionService): void
    {
        $batch = $this->batch();
        if ($batch !== null && $batch->cancelled()) {
            return;
        }

        $file = IngestionFile::query()->find($this->ingestionFile->getKey());

        if ($file === null) {
            return;
        }

        if (! $ocrExtractionService->supportsFile($file)) {
            return;
        }

        try {
            $result = $ocrExtractionService->extract($file);
        } catch (OcrExtractionException $e) {
            Log::warning('ingestion.ocr.failed', [
                'ingestion_file_id' => $file->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('ingestion.ocr.error', [
                'ingestion_file_id' => $file->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        OcrResult::query()->where('ingestion_file_id', $file->id)->delete();

        OcrResult::query()->create([
            'ingestion_file_id' => $file->id,
            'engine_name' => $result->engineName,
            'raw_text' => $result->rawText === '' ? null : $result->rawText,
            'structured_blocks' => $result->structuredBlocks,
            'tables_json' => null,
            'confidence' => $result->confidence,
        ]);
    }
}
