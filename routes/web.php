<?php

use App\Http\Controllers\Ingestion\IngestionFileStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/ingestion-files/{ingestion_file}/inline', [IngestionFileStreamController::class, 'inline'])
        ->name('ingestion.files.inline');
    Route::get('/ingestion-files/{ingestion_file}/download', [IngestionFileStreamController::class, 'download'])
        ->name('ingestion.files.download');
});
