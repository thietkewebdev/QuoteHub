<?php

namespace App\Services\Ingestion;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IngestionUploadValidator
{
    /**
     * @return array<string, mixed>
     */
    public static function validatedBatchPayload(array $data): array
    {
        if (array_key_exists('supplier_id', $data) && $data['supplier_id'] === '') {
            $data['supplier_id'] = null;
        }

        $channels = array_keys(config('ingestion.source_channels', []));

        $validator = Validator::make($data, [
            'source_channel' => ['required', 'string', 'max:64', Rule::in($channels)],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @throws ValidationException
     */
    public static function assertStagedFileAllowed(string $absolutePath, string $originalName): void
    {
        $maxKb = (int) config('ingestion.max_file_size_kb', 20_480);
        $maxBytes = $maxKb * 1024;

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw ValidationException::withMessages([
                'uploads' => __('The uploaded file could not be read.'),
            ]);
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'uploads' => __('Each file may not be greater than :max kilobytes.', ['max' => $maxKb]),
            ]);
        }

        $allowedMimes = config('ingestion.allowed_mime_types', []);
        $allowedExtensions = array_map('strtolower', config('ingestion.allowed_extensions', []));

        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'uploads' => __('File type not allowed for :name.', ['name' => $originalName]),
            ]);
        }

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'uploads' => __('MIME type not allowed for :name.', ['name' => $originalName]),
            ]);
        }
    }
}
