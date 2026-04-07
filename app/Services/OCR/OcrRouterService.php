<?php

namespace App\Services\OCR;

/**
 * Routes local files to Vision (raster images) or Document AI (PDF).
 */
final class OcrRouterService
{
    public function __construct(
        private readonly GoogleVisionOcrService $vision,
        private readonly GoogleDocumentAiOcrService $documentAi,
    ) {}

    /**
     * @return array{
     *     provider: string,
     *     full_text: string,
     *     pages: list<array<string, mixed>>
     * }
     */
    public function extract(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new \InvalidArgumentException(__('File not found: :path', ['path' => $absolutePath]));
        }

        $mime = $this->detectMimeType($absolutePath);

        return match ($mime) {
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/bmp' => $this->vision->detectDocumentText($absolutePath),
            'application/pdf' => $this->documentAi->processLocalFile($absolutePath, $mime),
            default => throw new UnsupportedOcrMimeTypeException(
                __('Unsupported MIME type for OCR router: :mime', ['mime' => $mime])
            ),
        };
    }

    private function detectMimeType(string $absolutePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new \RuntimeException(__('Could not initialize MIME detection.'));
        }

        try {
            $mime = finfo_file($finfo, $absolutePath);
        } finally {
            finfo_close($finfo);
        }

        return strtolower(trim((string) $mime));
    }
}
