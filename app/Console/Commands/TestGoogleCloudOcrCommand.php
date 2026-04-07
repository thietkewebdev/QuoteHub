<?php

namespace App\Console\Commands;

use App\Services\OCR\Google\GoogleCloudOcrService;
use App\Services\OCR\Google\GoogleOcrException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Local smoke test for Google Vision / Document AI (no ingestion batch, no quotation approval).
 */
class TestGoogleCloudOcrCommand extends Command
{
    protected $signature = 'quotehub:test-google-ocr
                            {path : Absolute or project-relative path to a PDF or image file}
                            {--json : Print JSON debug payload only}';

    protected $description = 'Run Google Cloud OCR on a local file (Vision for images, Document AI for PDF)';

    public function handle(GoogleCloudOcrService $googleOcr): int
    {
        $rawPath = (string) $this->argument('path');
        $absolute = $this->resolvePath($rawPath);
        if ($absolute === null) {
            return self::FAILURE;
        }

        try {
            $result = $googleOcr->extractFile($absolute);
        } catch (GoogleOcrException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result->toDebugArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Engine: '.$result->engineName);
        $this->line('MIME: '.$result->mimeType);
        $this->line('Text length: '.mb_strlen($result->rawText));
        if ($result->confidence !== null) {
            $this->line('Confidence (avg.): '.(string) $result->confidence);
        }
        $tableCount = is_array($result->tables) ? count($result->tables) : 0;
        $this->line('Tables: '.$tableCount);
        $this->newLine();
        $this->comment('--- raw text (first 2000 chars) ---');
        $this->line(mb_substr($result->rawText, 0, 2000));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): ?string
    {
        if (is_file($path)) {
            return $path;
        }

        $base = base_path($path);
        if (is_file($base)) {
            return $base;
        }

        $this->error(__('File not found: :path', ['path' => $path]));

        return null;
    }
}
