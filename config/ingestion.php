<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Max upload size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Applied to each file in an ingestion batch. Override with INGESTION_MAX_FILE_KB.
    |
    */
    'max_file_size_kb' => (int) env('INGESTION_MAX_FILE_KB', 20_480),

    /*
    |--------------------------------------------------------------------------
    | Staging directory (relative to the configured disk root)
    |--------------------------------------------------------------------------
    |
    | Filament uploads land here first; files are moved under ingestion/{batch_id}/.
    |
    */
    'staging_directory' => 'tmp/ingestion_uploads',

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    */
    'disk' => env('INGESTION_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Allowed source channels (value => label for UI)
    |--------------------------------------------------------------------------
    */
    'source_channels' => [
        'email' => 'Email',
        'zalo' => 'Zalo',
        'portal' => 'Staff portal',
        'api' => 'API',
        'manual' => 'Manual',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types (primary gate; extension is also checked)
    |--------------------------------------------------------------------------
    */
    'allowed_mime_types' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed file extensions (without dot, lowercase)
    |--------------------------------------------------------------------------
    */
    'allowed_extensions' => ['pdf', 'xlsx', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | View ingestion batch — auto-refresh while OCR / AI jobs run
    |--------------------------------------------------------------------------
    */
    'view_batch_status_poll_seconds' => max(2, min(60, (int) env('INGESTION_VIEW_BATCH_POLL_SECONDS', 3))),

    /*
    |--------------------------------------------------------------------------
    | Auto-queue OCR (+ AI) after creating an ingestion batch
    |--------------------------------------------------------------------------
    |
    | When true (default), a successful Filament "create batch" queues the same
    | pipeline as "Run OCR & AI" after the create transaction commits.
    | Skipped when QUEUE_CONNECTION is sync or null so the web request is not
    | blocked by inline OCR. On Render, run a worker (e.g. php artisan queue:work).
    |
    */
    'auto_dispatch_ocr_after_create' => filter_var(
        env('INGESTION_AUTO_DISPATCH_OCR_AFTER_CREATE', true),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | Google OCR (Document AI / Vision) for ingestion
    |--------------------------------------------------------------------------
    |
    | When enabled (default: true), PDF/images use {@see \App\Services\OCR\OcrRouterService}
    | after upload ({@see \App\Services\Ingestion\IngestionGoogleOcrDraftService}) to seed review drafts.
    | Queued OCR ({@see \App\Jobs\OCR\RunOcrForFileJob}) uses Google Document AI / Vision only (no Tesseract fallback).
    |
    | {@see \App\Services\Ingestion\IngestionGoogleOcrDraftService} runs after upload when enabled.
    | With an async queue it runs in {@see \App\Jobs\Ingestion\CaptureGoogleOcrDraftForBatchJob} so the create form
    | does not block on Document AI (avoids Livewire timeouts on slow PDFs).
    | Set INGESTION_GOOGLE_OCR=false to disable (or legacy INGESTION_GOOGLE_OCR_AFTER_UPLOAD=false).
    | Requires GCP in config/services.php (gcp.*) and GOOGLE_APPLICATION_CREDENTIALS.
    |
    */
    'google_ocr' => [
        'enabled' => filter_var(
            env('INGESTION_GOOGLE_OCR', env('INGESTION_GOOGLE_OCR_AFTER_UPLOAD', true)),
            FILTER_VALIDATE_BOOL
        ),
    ],

];
