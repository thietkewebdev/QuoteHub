<?php

namespace App\Services\OCR;

use App\Models\IngestionFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Quotation ingestion OCR uses Google Document AI (PDF) and Google Vision (raster images) only.
 */
class OcrExtractionService
{
    public function __construct(
        protected OcrRouterService $ocrRouter,
    ) {}

    /**
     * @throws OcrExtractionException
     */
    public function extract(IngestionFile $ingestionFile): OcrExtractionResult
    {
        if (! $this->supportsFile($ingestionFile)) {
            throw new OcrExtractionException(__('This file type is not supported for OCR in Quote Hub.'));
        }

        $absolutePath = $this->absolutePath($ingestionFile);
        if ($absolutePath === null) {
            throw new OcrExtractionException(__('File is missing from storage.'));
        }

        try {
            $payload = $this->ocrRouter->extract($absolutePath);
        } catch (Throwable $e) {
            throw new OcrExtractionException(
                __('Google OCR failed: :message', ['message' => $e->getMessage()]),
                (int) $e->getCode(),
                $e
            );
        }

        if (! $this->googlePayloadHasUsableContent($payload)) {
            throw new OcrExtractionException(__('Google OCR returned no usable structured content for this file.'));
        }

        return $this->mapGooglePayloadToResult($payload);
    }

    /**
     * @deprecated Use {@see extract()} — kept for backward compatibility.
     */
    public function extractForFile(IngestionFile $ingestionFile): ?string
    {
        try {
            return $this->extract($ingestionFile)->rawText;
        } catch (OcrExtractionException) {
            return null;
        }
    }

    public function supportsFile(IngestionFile $ingestionFile): bool
    {
        $mime = (string) $ingestionFile->mime_type;

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return true;
        }

        return $mime === 'application/pdf';
    }

    protected function absolutePath(IngestionFile $ingestionFile): ?string
    {
        $relative = $ingestionFile->storage_path;
        if (blank($relative)) {
            return null;
        }

        $disk = Storage::disk((string) config('ingestion.disk', 'local'));

        if (! $disk->exists($relative)) {
            return null;
        }

        return $disk->path($relative);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function googlePayloadHasUsableContent(array $payload): bool
    {
        $text = trim((string) ($payload['full_text'] ?? ''));
        if ($text !== '') {
            return true;
        }

        $pages = $payload['pages'] ?? [];

        return is_array($pages) && $pages !== [] && trim(GoogleOcrStructuredDocumentCompiler::compilePages($pages)) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapGooglePayloadToResult(array $payload): OcrExtractionResult
    {
        $provider = (string) ($payload['provider'] ?? '');
        $engineName = match ($provider) {
            'google_document_ai' => 'google-document-ai',
            'google_vision' => 'google-vision',
            default => $provider !== '' ? $provider : 'google-router',
        };

        $text = trim((string) ($payload['full_text'] ?? ''));
        if ($text === '' && is_array($payload['pages'] ?? null)) {
            $text = GoogleOcrStructuredDocumentCompiler::compilePages($payload['pages']);
        }

        return new OcrExtractionResult(
            engineName: $engineName,
            rawText: $text,
            confidence: $this->averageConfidenceFromGooglePages(is_array($payload['pages'] ?? null) ? $payload['pages'] : []),
            structuredBlocks: [
                'source' => $provider,
                'pages' => $payload['pages'] ?? [],
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private function averageConfidenceFromGooglePages(array $pages): ?float
    {
        $scores = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($page['blocks'] ?? [] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (isset($block['confidence']) && is_numeric($block['confidence'])) {
                    $scores[] = (float) $block['confidence'];
                }
                foreach ($block['paragraphs'] ?? [] as $para) {
                    if (is_array($para) && isset($para['confidence']) && is_numeric($para['confidence'])) {
                        $scores[] = (float) $para['confidence'];
                    }
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 6);
    }
}
