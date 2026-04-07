<?php

namespace App\Services\Ingestion;

use App\Models\IngestionBatch;
use App\Models\IngestionFile;
use App\Models\QuotationReviewDraft;
use App\Services\OCR\OcrRouterService;
use App\Services\OCR\UnsupportedOcrMimeTypeException;
use App\Support\Ingestion\IngestionFileLocalMaterializer;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs {@see OcrRouterService} synchronously after upload and merges raw capture into {@see QuotationReviewDraft::$payload_json}.
 */
final class IngestionGoogleOcrDraftService
{
    public function __construct(
        private readonly OcrRouterService $ocrRouter,
        private readonly QuotationReviewPayloadFactory $payloadFactory,
    ) {}

    public function captureForBatch(IngestionBatch $batch): void
    {
        if (! (bool) config('ingestion.google_ocr.enabled', true)) {
            return;
        }

        $batch->loadMissing(['files']);

        $ocrFiles = $batch->files
            ->sortBy(fn (IngestionFile $f): array => [$f->page_order, $f->id])
            ->values()
            ->filter(fn (IngestionFile $f): bool => $this->supportsGoogleRouterOcr($f));

        if ($ocrFiles->isEmpty()) {
            $this->persistDraft($batch, $this->buildSkippedPayload($batch, __('No PDF or raster image files in this batch for Google OCR.')));

            return;
        }

        $textParts = [];
        $mergedPages = [];
        $mergedBlocks = [];
        $mergedTables = [];
        $sourcePaths = [];
        $providers = [];

        foreach ($ocrFiles as $file) {
            $relative = (string) $file->storage_path;
            [$absolute, $cleanup] = IngestionFileLocalMaterializer::pathForProcessing(
                $relative,
                (string) config('ingestion.disk', 'local'),
            );

            if ($absolute === null) {
                Log::warning('ingestion.google_ocr.file_missing', [
                    'ingestion_batch_id' => $batch->id,
                    'ingestion_file_id' => $file->id,
                    'path' => $relative,
                ]);

                continue;
            }

            try {
                $result = $this->ocrRouter->extract($absolute);
            } catch (UnsupportedOcrMimeTypeException $e) {
                Log::warning('ingestion.google_ocr.unsupported_mime', [
                    'ingestion_batch_id' => $batch->id,
                    'ingestion_file_id' => $file->id,
                    'message' => $e->getMessage(),
                ]);

                continue;
            } catch (Throwable $e) {
                Log::error('ingestion.google_ocr.extract_failed', [
                    'ingestion_batch_id' => $batch->id,
                    'ingestion_file_id' => $file->id,
                    'message' => $e->getMessage(),
                ]);
                $this->persistDraft($batch, $this->buildFailedPayload($batch, $e->getMessage()));

                return;
            } finally {
                if ($cleanup !== null) {
                    ($cleanup)();
                }
            }

            $provider = (string) ($result['provider'] ?? '');
            $providers[] = $provider;
            $sourcePaths[] = $relative;
            $ft = trim((string) ($result['full_text'] ?? ''));
            if ($ft !== '') {
                $textParts[] = $ft;
            }

            $pages = is_array($result['pages'] ?? null) ? $result['pages'] : [];
            foreach ($pages as $page) {
                if (! is_array($page)) {
                    continue;
                }
                $mergedPages[] = array_merge($page, [
                    '_ingestion_file_id' => $file->id,
                    '_original_name' => $file->original_name,
                    '_storage_path' => $relative,
                ]);
            }

            foreach ($pages as $page) {
                if (! is_array($page)) {
                    continue;
                }
                foreach ($page['blocks'] ?? [] as $block) {
                    if (is_array($block)) {
                        $mergedBlocks[] = array_merge($block, [
                            '_ingestion_file_id' => $file->id,
                            '_storage_path' => $relative,
                        ]);
                    }
                }
                foreach ($page['tables'] ?? [] as $table) {
                    if (is_array($table)) {
                        $mergedTables[] = array_merge($table, [
                            '_ingestion_file_id' => $file->id,
                            '_storage_path' => $relative,
                        ]);
                    }
                }
            }
        }

        if ($sourcePaths === []) {
            $this->persistDraft($batch, $this->buildSkippedPayload($batch, __('Google OCR could not read any stored files.')));

            return;
        }

        $uniqueProviders = array_values(array_unique(array_filter($providers)));
        $ocrProvider = count($uniqueProviders) === 1 ? ($uniqueProviders[0] ?? '') : 'mixed';

        $payload = $this->basePayload($batch);
        $payload['source_file_path'] = $sourcePaths[0];
        $payload['ocr_source_files'] = $sourcePaths;
        $payload['ocr_provider'] = $ocrProvider;
        $payload['ocr_processor_type'] = $this->processorType($ocrProvider);
        $payload['raw_full_text'] = implode("\n\n---\n\n", $textParts);
        $payload['raw_pages'] = $mergedPages;
        $payload['raw_blocks'] = $mergedBlocks;
        $payload['raw_tables'] = $mergedTables;
        $payload['extraction_status'] = [
            'ocr' => 'ocr_completed',
            'normalization' => 'no_normalization_yet',
        ];
        $payload['ocr_captured_at'] = now()->toIso8601String();
        $payload['ocr_error'] = null;

        $this->persistDraft($batch, $payload);
    }

    private function supportsGoogleRouterOcr(IngestionFile $file): bool
    {
        $mime = strtolower((string) $file->mime_type);

        return $mime === 'application/pdf'
            || in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/bmp'], true);
    }

    private function processorType(string $provider): string
    {
        return match ($provider) {
            'google_document_ai' => 'document_ai',
            'google_vision' => 'vision',
            'mixed' => 'mixed',
            default => $provider !== '' ? 'unknown' : '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(IngestionBatch $batch): array
    {
        $draft = QuotationReviewDraft::query()->where('ingestion_batch_id', $batch->id)->first();
        $prev = is_array($draft?->payload_json) ? $draft->payload_json : [];

        return array_merge($this->payloadFactory->emptyPayload(), $prev);
    }

    /**
     * @param  array<string, mixed>  $payloadJson  full payload_json (review scaffold + OCR capture fields)
     */
    private function persistDraft(IngestionBatch $batch, array $payloadJson): void
    {
        $batch->loadMissing(['aiExtraction']);

        QuotationReviewDraft::query()->updateOrCreate(
            ['ingestion_batch_id' => $batch->id],
            [
                'ai_extraction_id' => $batch->aiExtraction?->id,
                'payload_json' => $payloadJson,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSkippedPayload(IngestionBatch $batch, string $reason): array
    {
        $p = $this->basePayload($batch);
        $p['source_file_path'] = null;
        $p['ocr_source_files'] = [];
        $p['ocr_provider'] = '';
        $p['ocr_processor_type'] = '';
        $p['raw_full_text'] = '';
        $p['raw_pages'] = [];
        $p['raw_blocks'] = [];
        $p['raw_tables'] = [];
        $p['extraction_status'] = [
            'ocr' => 'skipped_no_supported_files',
            'normalization' => 'no_normalization_yet',
        ];
        $p['ocr_captured_at'] = now()->toIso8601String();
        $p['ocr_error'] = $reason;

        return $p;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFailedPayload(IngestionBatch $batch, string $message): array
    {
        $p = $this->buildSkippedPayload($batch, $message);
        $p['extraction_status'] = [
            'ocr' => 'ocr_failed',
            'normalization' => 'no_normalization_yet',
        ];

        return $p;
    }
}
