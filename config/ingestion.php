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

];
