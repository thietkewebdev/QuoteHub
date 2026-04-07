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

        foreach ($stagedRelativePaths as $index => $relativePath) {
            $relativePath = $this->normalizeRelativePath($relativePath);
            $absolutePath = $this->ingestionDisk->path($relativePath);

            $originalName = $originalNames[$index] ?? basename($relativePath);

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

            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = (string) Str::ulid().($extension !== '' ? '.'.$extension : '');
            $destinationRelative = 'ingestion/'.$batch->id.'/'.$storedName;

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

            $ingestionDiskName = config('ingestion.disk', 'local');
            $driver = (string) config('filesystems.disks.'.$ingestionDiskName.'.driver', 'local');

            if ($driver === 'local') {
                $finalPath = $this->ingestionDisk->path($destinationRelative);
                $mime = mime_content_type($finalPath) ?: 'application/octet-stream';
                $fileSize = filesize($finalPath);
                [$width, $height] = $this->imageDimensions($finalPath, $mime);
            } else {
                $mime = $this->ingestionDisk->mimeType($destinationRelative) ?: 'application/octet-stream';
                $fileSize = $this->ingestionDisk->size($destinationRelative);
                [$width, $height] = $this->imageDimensionsRemote($destinationRelative, $mime);
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

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function imageDimensionsRemote(string $relativePath, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $binary = $this->ingestionDisk->get($relativePath);
        if (! is_string($binary) || $binary === '') {
            return [null, null];
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }
}
