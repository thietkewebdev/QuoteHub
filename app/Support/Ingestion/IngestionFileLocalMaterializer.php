<?php

declare(strict_types=1);

namespace App\Support\Ingestion;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Google OCR and MIME checks need a real filesystem path. On Render, the web and worker
 * containers do not share local disk — use a shared disk (e.g. s3) and materialize to temp here.
 */
final class IngestionFileLocalMaterializer
{
    /**
     * @return array{0: string, 1: \Closure|null} Absolute path and optional cleanup (unlink), or [null, null] if missing.
     */
    public static function pathForProcessing(string $relativePath, ?string $diskName = null): array
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return [null, null];
        }

        $name = $diskName ?? (string) config('ingestion.disk', 'local');
        $disk = Storage::disk($name);

        if (! $disk->exists($relativePath)) {
            return [null, null];
        }

        $driver = (string) config('filesystems.disks.'.$name.'.driver', 'local');

        if ($driver === 'local') {
            $path = $disk->path($relativePath);

            return is_file($path) && is_readable($path) ? [$path, null] : [null, null];
        }

        $dir = storage_path('app/tmp/ingestion-materialize');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [null, null];
        }

        $tmp = $dir.'/'.hash('sha256', $name.'|'.$relativePath).'-'.preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($relativePath));
        $stream = $disk->readStream($relativePath);
        if ($stream === false) {
            return [null, null];
        }

        $out = fopen($tmp, 'wb');
        if ($out === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return [null, null];
        }

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);

            return [null, null];
        }

        $cleanup = static function () use ($tmp): void {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        };

        return [$tmp, $cleanup];
    }
}
