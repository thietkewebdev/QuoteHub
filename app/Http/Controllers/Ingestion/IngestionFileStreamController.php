<?php

namespace App\Http\Controllers\Ingestion;

use App\Http\Controllers\Controller;
use App\Models\IngestionFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngestionFileStreamController extends Controller
{
    public function inline(IngestionFile $ingestionFile): StreamedResponse
    {
        $this->authorize('view', $ingestionFile);

        abort_unless($ingestionFile->supportsInlinePreview(), 404);

        return $this->respondWithFile($ingestionFile, inline: true);
    }

    public function download(IngestionFile $ingestionFile): StreamedResponse
    {
        $this->authorize('download', $ingestionFile);

        return $this->respondWithFile($ingestionFile, inline: false);
    }

    /**
     * Stream from the configured disk (local or S3-compatible e.g. Cloudflare R2).
     * {@see FilesystemAdapter::readStream} works for both.
     */
    protected function respondWithFile(IngestionFile $ingestionFile, bool $inline): StreamedResponse
    {
        $diskName = config('ingestion.disk', 'local');
        $disk = Storage::disk($diskName);
        $relative = $ingestionFile->storage_path;

        abort_if(blank($relative) || ! $disk->exists($relative), 404);

        $mime = $ingestionFile->mime_type ?: 'application/octet-stream';
        $filename = $this->asciiFilename($ingestionFile->original_name ?? basename($relative));

        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $inline
            ? $disk->response($relative, $filename, $headers, 'inline')
            : $disk->download($relative, $filename, $headers);
    }

    protected function asciiFilename(string $name): string
    {
        $ascii = Str::ascii($name);

        return $ascii !== '' ? $ascii : 'file';
    }
}
