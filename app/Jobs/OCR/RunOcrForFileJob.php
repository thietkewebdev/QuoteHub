<?php

namespace App\Jobs\OCR;

use App\Models\IngestionFile;
use App\Services\OCR\OcrExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder job: wire to {@see OcrExtractionService} when OCR is implemented.
 */
class RunOcrForFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public IngestionFile $ingestionFile) {}

    public function handle(OcrExtractionService $ocrExtractionService): void
    {
        $file = IngestionFile::query()->find($this->ingestionFile->getKey());

        if ($file === null) {
            return;
        }

        $ocrExtractionService->extractForFile($file);
    }
}
