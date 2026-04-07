<?php

namespace App\Services\Ingestion;

use App\Models\IngestionBatch;
use App\Models\IngestionFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IngestionFileStorageService
{
    public function __construct(
        protected Filesystem $ingestionDisk,
    ) {}

    public static function makeFromConfig(): self
    {
        $diskName = config('ingestion.disk', 'local');

        return new self(Storage::disk($diskName));
    }

    /**
     * Move staged uploads (paths relative to disk root) into ingestion/{batch_id}/,
     * create ingestion_files rows, dedupe by SHA-256 within the batch, then mark batch uploaded.
     *
     * @param  list<string>  $stagedRelativePaths
     * @param  list<string>|null  $originalNames  Same order as paths; missing entries fall back to basename
     * @return array{files: Collection<int, IngestionFile>, skipped_duplicates: int}
     */
    public function persistStagedUploads(
        IngestionBatch $batch,
        array $stagedRelativePaths,
        ?array $originalNames,
    ): array {
        $originalNames ??= [];

        if ($stagedRelativePaths === []) {
            throw ValidationException::withMessages([
                'uploads' => __('Add at least one file.'),
            ]);
        }

        $seenChecksums = [];
        $stored = collect();
        $skippedDuplicates = 0;
        $pageOrder = 0;

        $ingestionDiskName = config('ingestion.disk', 'local');
        $driver = (string) config('filesystems.disks.'.$ingestionDiskName.'.driver', 'local');

        foreach ($stagedRelativePaths as $index => $relativePath) {
            $relativePath = $this->normalizeRelativePath($relativePath);

            $originalName = $originalNames[$index] ?? basename($relativePath);

            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = (string) Str::ulid().($extension !== '' ? '.'.$extension : '');
            $destinationRelative = 'ingestion/'.$batch->id.'/'.$storedName;

            if ($driver === 'local') {
                $absolutePath = $this->ingestionDisk->path($relativePath);

                IngestionUploadValidator::assertStagedFileAllowed($absolutePath, $originalName);

                $checksum = hash_file('sha256', $absolutePath);
                if ($checksum === false) {
                    throw ValidationException::withMessages([
                        'uploads' => __('Could not hash :name.', ['name' => $originalName]),
                    ]);
                }

                if (isset($seenChecksums[$checksum])) {
                    $skippedDuplicates++;

                    continue;
                }

                $seenChecksums[$checksum] = true;

                $stream = $this->ingestionDisk->readStream($relativePath);
                if ($stream === false) {
                    throw ValidationException::withMessages([
                        'uploads' => __('Could not read staged file for :name.', ['name' => $originalName]),
                    ]);
                }

                try {
                    if (! $this->ingestionDisk->writeStream($destinationRelative, $stream)) {
                        throw ValidationException::withMessages([
                            'uploads' => __('Could not store :name.', ['name' => $originalName]),
                        ]);
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $finalPath = $this->ingestionDisk->path($destinationRelative);
                $mime = mime_content_type($finalPath) ?: 'application/octet-stream';
                $fileSize = filesize($finalPath);
                [$width, $height] = $this->imageDimensions($finalPath, $mime);
            } else {
                $remote = $this->loadRemoteStagedFile($relativePath, $originalName);
                $checksum = $remote['checksum'];

                if (isset($seenChecksums[$checksum])) {
                    $skippedDuplicates++;

                    continue;
                }

                $seenChecksums[$checksum] = true;

                if (! $this->ingestionDisk->put($destinationRelative, $remote['binary'])) {
                    throw ValidationException::withMessages([
                        'uploads' => __('Could not store :name.', ['name' => $originalName]),
                    ]);
                }

                $mime = $remote['mime'];
                $fileSize = $remote['file_size'];
                [$width, $height] = $this->imageDimensionsFromBinary($remote['binary'], $mime);
            }

            $file = IngestionFile::query()->create([
                'ingestion_batch_id' => $batch->id,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'extension' => $extension !== '' ? $extension : null,
                'storage_path' => $destinationRelative,
                'checksum_sha256' => $checksum,
                'page_order' => $pageOrder,
                'file_size' => $fileSize !== false ? $fileSize : null,
                'width' => $width,
                'height' => $height,
                'preprocessing_meta' => null,
            ]);

            $stored->push($file);
            $pageOrder++;
        }

        if ($stored->isEmpty()) {
            throw ValidationException::withMessages([
                'uploads' => __('All selected files were duplicates of another file in this upload.'),
            ]);
        }

        $batch->forceFill([
            'file_count' => $stored->count(),
            'status' => 'uploaded',
        ])->save();

        return [
            'files' => $stored,
            'skipped_duplicates' => $skippedDuplicates,
        ];
    }

    /**
     * @param  list<string>  $stagedRelativePaths
     */
    public function deleteStagedRelativePaths(array $stagedRelativePaths): void
    {
        foreach ($stagedRelativePaths as $path) {
            $path = $this->normalizeRelativePath($path);
            if ($path !== '' && $this->ingestionDisk->exists($path)) {
                $this->ingestionDisk->delete($path);
            }
        }
    }

    protected function normalizeRelativePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Read staged object from S3/R2 (or any non-local disk), validate size/type, return bytes for a single PUT to final path.
     *
     * @return array{checksum: string, mime: string, file_size: int, binary: string}
     */
    protected function loadRemoteStagedFile(string $relativePath, string $originalName): array
    {
        $maxKb = (int) config('ingestion.max_file_size_kb', 20_480);
        $maxBytes = $maxKb * 1024;

        if (! $this->ingestionDisk->exists($relativePath)) {
            throw ValidationException::withMessages([
                'uploads' => __('The uploaded file could not be read.'),
            ]);
        }

        $reportedSize = $this->ingestionDisk->size($relativePath);
        if ($reportedSize > $maxBytes) {
            throw ValidationException::withMessages([
                'uploads' => __('Each file may not be greater than :max kilobytes.', ['max' => $maxKb]),
            ]);
        }

        $binary = $this->ingestionDisk->get($relativePath);
        if (! is_string($binary)) {
            throw ValidationException::withMessages([
                'uploads' => __('The uploaded file could not be read.'),
            ]);
        }

        if (strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages([
                'uploads' => __('Each file may not be greater than :max kilobytes.', ['max' => $maxKb]),
            ]);
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = array_map('strtolower', config('ingestion.allowed_extensions', []));

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'uploads' => __('File type not allowed for :name.', ['name' => $originalName]),
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary) ?: 'application/octet-stream';
        $mime = $this->normalizeRemoteDetectedMime($mime, $extension);

        $allowedMimes = config('ingestion.allowed_mime_types', []);
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'uploads' => __('MIME type not allowed for :name.', ['name' => $originalName]),
            ]);
        }

        return [
            'checksum' => hash('sha256', $binary),
            'mime' => $mime,
            'file_size' => strlen($binary),
            'binary' => $binary,
        ];
    }

    /**
     * finfo often reports application/zip or application/octet-stream for Office/OpenXML and PDFs.
     */
    protected function normalizeRemoteDetectedMime(string $detectedMime, string $extension): string
    {
        $allowedMimes = config('ingestion.allowed_mime_types', []);
        if (in_array($detectedMime, $allowedMimes, true)) {
            return $detectedMime;
        }

        $byExt = match ($extension) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };

        if ($byExt !== null && in_array($byExt, $allowedMimes, true)) {
            if ($detectedMime === 'application/octet-stream'
                || $detectedMime === 'application/zip'
                || str_starts_with($detectedMime, 'image/')) {
                return $byExt;
            }
        }

        return $detectedMime;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function imageDimensionsFromBinary(string $binary, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function imageDimensions(string $absolutePath, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }
}
