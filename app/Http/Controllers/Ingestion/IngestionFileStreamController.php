<?php

namespace App\Http\Controllers\Ingestion;

use App\Http\Controllers\Controller;
use App\Models\IngestionFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IngestionFileStreamController extends Controller
{
    public function inline(IngestionFile $ingestionFile): BinaryFileResponse
    {
        $this->authorize('view', $ingestionFile);

        abort_unless($ingestionFile->supportsInlinePreview(), 404);

        return $this->respondWithFile($ingestionFile, inline: true);
    }

    public function download(IngestionFile $ingestionFile): BinaryFileResponse
    {
        $this->authorize('download', $ingestionFile);

        return $this->respondWithFile($ingestionFile, inline: false);
    }

    protected function respondWithFile(IngestionFile $ingestionFile, bool $inline): BinaryFileResponse
    {
        $diskName = config('ingestion.disk', 'local');
        $disk = Storage::disk($diskName);
        $relative = $ingestionFile->storage_path;

        abort_if(blank($relative) || ! $disk->exists($relative), 404);

        $absolutePath = $disk->path($relative);
        $mime = $ingestionFile->mime_type ?: 'application/octet-stream';
        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $this->asciiFilename($ingestionFile->original_name ?? basename($relative));

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function asciiFilename(string $name): string
    {
        $ascii = Str::ascii($name);

        return $ascii !== '' ? $ascii : 'file';
    }
}
