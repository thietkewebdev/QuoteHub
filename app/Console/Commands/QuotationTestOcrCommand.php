<?php

namespace App\Console\Commands;

use App\Services\OCR\OcrRouterService;
use App\Services\OCR\UnsupportedOcrMimeTypeException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Standalone OCR smoke test via OcrRouterService (no quotation extraction).
 */
class QuotationTestOcrCommand extends Command
{
    protected $signature = 'quotation:test-ocr
                            {file : Absolute or project-relative path to a PDF or image file}';

    protected $description = 'Run OCR router on a local file and write JSON to storage/app/ocr-test-output.json';

    public function handle(OcrRouterService $router): int
    {
        $rawPath = (string) $this->argument('file');
        $absolute = $this->resolvePath($rawPath);
        if ($absolute === null) {
            return self::FAILURE;
        }

        try {
            $payload = $router->extract($absolute);
        } catch (UnsupportedOcrMimeTypeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $outPath = storage_path('app/ocr-test-output.json');
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($outPath, $json) === false) {
            $this->error(__('Could not write :path', ['path' => $outPath]));

            return self::FAILURE;
        }

        $pages = $payload['pages'] ?? [];
        $pageCount = count($pages);
        $blockCount = 0;
        $tableCount = 0;
        foreach ($pages as $page) {
            $blockCount += count($page['blocks'] ?? []);
            $tableCount += count($page['tables'] ?? []);
        }

        $fullText = (string) ($payload['full_text'] ?? '');
        $preview = mb_substr($fullText, 0, 1000);

        $this->info('OCR test complete.');
        $this->line('Provider: '.($payload['provider'] ?? ''));
        $this->line('Pages: '.$pageCount);
        $this->line('Blocks (total): '.$blockCount);
        $this->line('Tables (total): '.$tableCount);
        $this->newLine();
        $this->comment('full_text (first 1000 chars):');
        $this->line($preview !== '' ? $preview : '(empty)');
        $this->newLine();
        $this->line('JSON: '.$outPath);

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
