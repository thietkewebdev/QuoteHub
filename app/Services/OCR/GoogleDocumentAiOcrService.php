<?php

namespace App\Services\OCR;

use Google\ApiCore\ApiException;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\Document;
use Google\Cloud\DocumentAI\V1\Document\Page\Layout;
use Google\Cloud\DocumentAI\V1\Document\Page\Table;
use Google\Cloud\DocumentAI\V1\Document\Page\Table\TableRow;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;

/**
 * Google Document AI — synchronous process on local PDF or image bytes.
 */
final class GoogleDocumentAiOcrService
{
    public const PROVIDER = 'google_document_ai';

    /**
     * @param  non-empty-string  $mimeType  e.g. application/pdf, image/jpeg
     * @return array{
     *     provider: string,
     *     full_text: string,
     *     pages: list<array{blocks: list<array<string, mixed>>, tables: list<array<string, mixed>>}>
     * }
     *
     * @throws ApiException
     */
    public function processLocalFile(string $absolutePath, string $mimeType): array
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException(__('File path is not readable: :path', ['path' => $absolutePath]));
        }

        $mimeType = strtolower(trim($mimeType));
        if (! $this->isSupportedMime($mimeType)) {
            throw new UnsupportedOcrMimeTypeException(
                __('Document AI OCR does not support MIME type: :mime', ['mime' => $mimeType])
            );
        }

        $content = file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            throw new \InvalidArgumentException(__('Could not read file bytes.'));
        }

        $processorName = $this->processorResourceName();
        $this->bootstrapCredentials();

        $raw = (new RawDocument)
            ->setContent($content)
            ->setMimeType($mimeType);

        $request = (new ProcessRequest)
            ->setName($processorName)
            ->setRawDocument($raw);

        $client = new DocumentProcessorServiceClient;
        try {
            $response = $client->processDocument($request);
        } finally {
            $client->close();
        }

        $document = $response->getDocument();
        if ($document === null) {
            return [
                'provider' => self::PROVIDER,
                'full_text' => '',
                'pages' => [],
            ];
        }

        return $this->documentToPayload($document);
    }

    /**
     * @return array{
     *     provider: string,
     *     full_text: string,
     *     pages: list<array{blocks: list<array<string, mixed>>, tables: list<array<string, mixed>>}>
     * }
     */
    private function documentToPayload(Document $document): array
    {
        $text = trim((string) $document->getText());
        $pagesOut = [];

        foreach ($document->getPages() as $page) {
            $blocks = [];
            foreach ($page->getBlocks() as $block) {
                $layout = $block->hasLayout() ? $block->getLayout() : null;
                $blocks[] = [
                    'text' => self::textFromLayout($text, $layout),
                    'confidence' => $layout instanceof Layout && $layout->getConfidence() > 0
                        ? round((float) $layout->getConfidence(), 6)
                        : null,
                ];
            }

            $tables = [];
            foreach ($page->getTables() as $table) {
                $tables[] = [
                    'rows' => self::tableToMatrix($document, $table),
                ];
            }

            $pagesOut[] = [
                'blocks' => $blocks,
                'tables' => $tables,
            ];
        }

        return [
            'provider' => self::PROVIDER,
            'full_text' => $text,
            'pages' => $pagesOut,
        ];
    }

    private function processorResourceName(): string
    {
        $project = trim((string) config('services.gcp.project_id', ''));
        $location = trim((string) config('services.gcp.location', ''));
        $processorId = trim((string) config('services.gcp.document_ai_processor_id', ''));

        if ($project === '' || $location === '' || $processorId === '') {
            throw new \InvalidArgumentException(
                __('Set GCP_PROJECT_ID, GCP_LOCATION, and GCP_DOCUMENT_AI_PROCESSOR_ID for Document AI.')
            );
        }

        return sprintf('projects/%s/locations/%s/processors/%s', $project, $location, $processorId);
    }

    private function bootstrapCredentials(): void
    {
        $path = config('services.gcp.credentials_path');
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $path = trim($path);
        if (is_file($path) && is_readable($path)) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$path);
            $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $path;
        }
    }

    private function isSupportedMime(string $mime): bool
    {
        return in_array($mime, [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/tiff',
            'image/bmp',
        ], true);
    }

    private static function textFromLayout(string $documentText, ?Layout $layout): string
    {
        if ($layout === null || ! $layout->hasTextAnchor()) {
            return '';
        }

        $anchor = $layout->getTextAnchor();
        $content = (string) $anchor->getContent();
        if ($content !== '') {
            return trim($content);
        }

        $out = '';
        foreach ($anchor->getTextSegments() as $segment) {
            $start = (int) $segment->getStartIndex();
            $end = (int) $segment->getEndIndex();
            if ($end > $start) {
                $out .= mb_substr($documentText, $start, $end - $start);
            }
        }

        return trim($out);
    }

    /**
     * @return list<list<string>>
     */
    private static function tableToMatrix(Document $document, Table $table): array
    {
        $text = (string) $document->getText();
        $rows = [];

        foreach ($table->getHeaderRows() as $row) {
            $rows[] = self::rowCellsToStrings($text, $row);
        }
        foreach ($table->getBodyRows() as $row) {
            $rows[] = self::rowCellsToStrings($text, $row);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function rowCellsToStrings(string $documentText, TableRow $row): array
    {
        $cells = [];
        foreach ($row->getCells() as $cell) {
            $layout = $cell->hasLayout() ? $cell->getLayout() : null;
            $cells[] = self::textFromLayout($documentText, $layout);
        }

        return $cells;
    }
}
