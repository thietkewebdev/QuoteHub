<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use App\Models\IngestionBatch;
use App\Models\IngestionFile;
use App\Services\OCR\GoogleOcrStructuredDocumentCompiler;
use App\Services\OCR\OcrExtractionService;
use Illuminate\Support\HtmlString;

/**
 * User-facing pipeline progress for ingestion batch view / list (OCR % when known; AI phase is indeterminate).
 */
final class IngestionBatchPipelineProgressPresenter
{
    /**
     * @return array{eligible: int, completed: int, percent: int|null} percent null when eligible is 0
     */
    public static function ocrProgressCounts(IngestionBatch $batch): array
    {
        $batch->loadMissing(['files.ocrResults']);

        $ocr = app(OcrExtractionService::class);

        $eligible = 0;
        $completed = 0;

        foreach ($batch->files as $file) {
            if (! $file instanceof IngestionFile) {
                continue;
            }

            if (! $ocr->supportsFile($file)) {
                continue;
            }

            $eligible++;

            if (self::fileHasUsableGoogleOcr($file)) {
                $completed++;
            }
        }

        if ($eligible === 0) {
            return ['eligible' => 0, 'completed' => 0, 'percent' => null];
        }

        $percent = (int) round(($completed / $eligible) * 100);

        return ['eligible' => $eligible, 'completed' => $completed, 'percent' => $percent];
    }

    public static function infolistProgressHtml(IngestionBatch $batch): HtmlString
    {
        return new HtmlString(self::renderBlock($batch, compact: false));
    }

    /**
     * Plain summary for table rows (Filament escapes description HTML).
     */
    public static function tableProgressPlainText(IngestionBatch $batch): ?string
    {
        $status = (string) $batch->status;

        if ($status === 'preprocessing') {
            $ocr = self::ocrProgressCounts($batch);
            if ($ocr['eligible'] === 0) {
                return __('No OCR-eligible files; waiting for batch status update.');
            }

            return __('OCR: :done / :total files (:percent%)', [
                'done' => $ocr['completed'],
                'total' => $ocr['eligible'],
                'percent' => $ocr['percent'] ?? 0,
            ]);
        }

        if ($status === 'ai_processing') {
            return __('AI extraction running — detailed progress is not available until the job finishes.');
        }

        return null;
    }

    private static function renderBlock(IngestionBatch $batch, bool $compact): string
    {
        $status = (string) $batch->status;

        if ($status === 'preprocessing') {
            $ocr = self::ocrProgressCounts($batch);
            $bar = self::determinateBar($ocr['percent'] ?? 0, $compact);
            if ($ocr['eligible'] === 0) {
                $line = e(__('No OCR-eligible files; waiting for batch status update.'));

                return self::wrap($bar.$line, $compact);
            }

            $label = __('OCR: :done / :total files (:percent%)', [
                'done' => $ocr['completed'],
                'total' => $ocr['eligible'],
                'percent' => $ocr['percent'] ?? 0,
            ]);

            return self::wrap($bar.'<p class="'.self::labelClass($compact).'">'.e($label).'</p>', $compact);
        }

        if ($status === 'ai_processing') {
            $ocr = self::ocrProgressCounts($batch);
            $ocrLine = $ocr['eligible'] > 0
                ? __('OCR complete (:done / :total files)', ['done' => $ocr['completed'], 'total' => $ocr['eligible']])
                : __('OCR skipped (no PDF/image files in batch)');

            $bar = self::indeterminateBar($compact);
            $aiLabel = __('AI extraction running — detailed progress is not available until the job finishes.');

            $html = '<p class="'.self::labelClass($compact).'"><span class="font-medium text-success-600 dark:text-success-400">✓</span> '.e($ocrLine).'</p>';
            $html .= $bar;
            $html .= '<p class="'.self::labelClass($compact).'">'.e($aiLabel).'</p>';

            return self::wrap($html, $compact);
        }

        return '';
    }

    private static function wrap(string $inner, bool $compact): string
    {
        $cls = $compact
            ? 'fi-ingestion-pipeline-progress mt-1 max-w-xs text-xs text-gray-600 dark:text-gray-400'
            : 'fi-ingestion-pipeline-progress mt-2 max-w-md space-y-2 text-sm text-gray-600 dark:text-gray-400';

        return '<div class="'.e($cls).'">'.$inner.'</div>';
    }

    private static function labelClass(bool $compact): string
    {
        return $compact ? 'mt-1 leading-snug' : 'leading-snug';
    }

    private static function determinateBar(int $percent, bool $compact): string
    {
        $percent = max(0, min(100, $percent));
        $h = $compact ? 'h-1.5' : 'h-2';

        return '<div class="'.$h.' w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-valuenow="'.(int) $percent.'" aria-valuemin="0" aria-valuemax="100" aria-label="'.e(__('OCR progress')).'">'
            .'<div class="h-full rounded-full bg-primary-600 transition-[width] duration-300 dark:bg-primary-500" style="width: '.(int) $percent.'%"></div>'
            .'</div>';
    }

    private static function indeterminateBar(bool $compact): string
    {
        $h = $compact ? 'h-1.5' : 'h-2';

        return '<div class="'.$h.' w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="'.e(__('AI extraction progress')).'">'
            .'<div class="fi-ingestion-progress-indeterminate h-full w-2/5 rounded-full bg-primary-600 motion-reduce:animate-none dark:bg-primary-500"></div>'
            .'</div>';
    }

    private static function fileHasUsableGoogleOcr(IngestionFile $file): bool
    {
        foreach ($file->ocrResults as $row) {
            if (GoogleOcrStructuredDocumentCompiler::ocrResultHasExtractableContent($row)) {
                return true;
            }
        }

        return false;
    }
}
