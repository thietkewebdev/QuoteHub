<?php

namespace App\Services\OCR\Google;

/**
 * Routes local files to Vision (images) or Document AI (PDF). Does not map to quotations.
 */
final class GoogleCloudOcrService
{
    public function __construct(
        private readonly GoogleVisionOcrService $vision,
        private readonly GoogleDocumentAiOcrService $documentAi,
    ) {}

    /**
     * @throws GoogleOcrException
     */
    public function extractFile(string $absolutePath, ?string $mimeType = null): GoogleOcrResult
    {
        if (! (bool) config('google_ocr.enabled', false)) {
            throw new GoogleOcrException(__('Google OCR is disabled. Set GOOGLE_OCR_ENABLED=true in .env.'));
        }

        GoogleCredentialsBootstrap::requirePath();

        if (! is_file($absolutePath)) {
            throw new GoogleOcrException(__('File not found: :path', ['path' => $absolutePath]));
        }

        $mime = $mimeType ?? $this->detectMime($absolutePath);

        return match ($mime) {
            'image/jpeg', 'image/png', 'image/webp', 'image/gif' => $this->vision->extractImage($absolutePath, $mime),
            'application/pdf' => $this->documentAi->extractPdf($absolutePath),
            default => throw new GoogleOcrException(__('Unsupported MIME type for Google OCR: :mime', ['mime' => $mime])),
        };
    }

    /**
     * @return list<string>
     */
    public static function supportedImageMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    private function detectMime(string $absolutePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new GoogleOcrException(__('Could not open finfo for MIME detection.'));
        }
        try {
            $mime = finfo_file($finfo, $absolutePath) ?: '';
        } finally {
            finfo_close($finfo);
        }

        return strtolower(trim((string) $mime));
    }
}
