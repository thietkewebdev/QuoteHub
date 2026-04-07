<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch (ingestion pipeline wiring comes later)
    |--------------------------------------------------------------------------
    */
    'enabled' => filter_var(env('GOOGLE_OCR_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Service account JSON key path (absolute, or relative to project root)
    |--------------------------------------------------------------------------
    | Also respected: GOOGLE_APPLICATION_CREDENTIALS (standard Google client env).
    */
    'credentials_path' => env('GOOGLE_OCR_CREDENTIALS_PATH', env('GOOGLE_APPLICATION_CREDENTIALS')),

    /*
    |--------------------------------------------------------------------------
    | Cloud Vision — images (JPEG/PNG/WebP/GIF)
    |--------------------------------------------------------------------------
    */
    'vision' => [
        'model' => env('GOOGLE_VISION_MODEL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document AI — PDF (full processor resource name)
    |--------------------------------------------------------------------------
    | Example: projects/PROJECT_ID/locations/us/processors/PROCESSOR_ID
    */
    'document_ai' => [
        'processor_name' => env('GOOGLE_DOCUMENT_AI_PROCESSOR_NAME', ''),
    ],

];
