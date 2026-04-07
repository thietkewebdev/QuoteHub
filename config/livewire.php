<?php

// Must match Filament FileUpload / ingestion max (Livewire temp upload validates before Filament sees the file).
$ingestionMaxKb = max(1, (int) env('INGESTION_MAX_FILE_KB', 20_480));

return [

    'class_namespace' => 'App\\Livewire',

    'view_path' => resource_path('views/livewire'),

    'layout' => 'components.layouts.app',

    'lazy_placeholder' => null,

    'temporary_file_upload' => [
        // null = config('filesystems.default'). Set LIVEWIRE_TMP_DISK=local to stage on server when default is s3 (fewer R2 round-trips).
        'disk' => env('LIVEWIRE_TMP_DISK'),
        'rules' => ['required', 'file', 'max:'.$ingestionMaxKb],
        'directory' => env('LIVEWIRE_TMP_DIRECTORY'),
        'middleware' => env('LIVEWIRE_UPLOAD_MIDDLEWARE'),
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'pdf',
        ],
        'max_upload_time' => (int) env('LIVEWIRE_MAX_UPLOAD_TIME', 15),
        'cleanup' => filter_var(env('LIVEWIRE_TMP_CLEANUP', true), FILTER_VALIDATE_BOOL),
    ],

    'render_on_redirect' => false,

    'legacy_model_binding' => false,

    'inject_assets' => true,

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    'inject_morph_markers' => true,

    'smart_wire_keys' => false,

    'pagination_theme' => 'tailwind',

    'release_token' => 'a',
];
