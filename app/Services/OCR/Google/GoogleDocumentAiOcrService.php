<?php

namespace App\Services\OCR\Google;

use Google\ApiCore\ApiException;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\Document;
use Google\Cloud\DocumentAI\V1\Document\Page\Layout;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;

/**
 * Google Document AI — synchronous ProcessDocument for PDF bytes (no LLM).
 */
final class GoogleDocumentAiOcrService
{
    public const ENGINE = 'google-document-ai';

    /**
     * @throws GoogleOcrException
     */
    public function extractPdf(string $absolutePath): GoogleOcrResult
    {
        $processor = trim((string) config('google_ocr.document_ai.processor_name', ''));
        if ($processor === '') {
            throw new GoogleOcrException(
                __('Set GOOGLE_DOCUMENT_AI_PROCESSOR_NAME to the full processor resource name (projects/…/locations/…/processors/…).')
            );
        }

        if (! is_readable($absolutePath)) {
            throw new GoogleOcrException(__('PDF file is not readable: :path', ['path' => $absolutePath]));
        }

        $content = file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            throw new GoogleOcrException(__('Could not read PDF bytes.'));
        }

        GoogleCredentialsBootstrap::apply();

        $raw = (new RawDocument)
            ->setContent($content)
            ->setMimeType('application/pdf');

        $request = (new ProcessRequest)
            ->setName($processor)
            ->setRawDocument($raw);

        try {
            $client = new DocumentProcessorServiceClient;
            $response = $client->processDocument($request);
            $client->close();
        } catch (ApiException $e) {
            throw new GoogleOcrException('Document AI: '.$e->getMessage(), (int) $e->getCode(), $e);
        }

        $document = $response->getDocument();
        if ($document === null) {
            throw new GoogleOcrException(__('Document AI returned an empty document.'));
        }

        return $this->documentToResult($document);
    }

    private function documentToResult(Document $document): GoogleOcrResult
    {
        $text = trim((string) $document->getText());
        $tables = [];
        $pageSummaries = [];

        foreach ($document->getPages() as $pageIndex => $page) {
            $blocks = [];
            foreach ($page->getBlocks() as $block) {
                $layout = $block->hasLayout() ? $block->getLayout() : null;
                $blocks[] = [
                    'text' => DocumentAiLayoutText::fromLayout($text, $layout),
                    'confidence' => $layout instanceof Layout ? $layout->getConfidence() : null,
                ];
            }

            $pageTables = [];
            foreach ($page->getTables() as $tIndex => $table) {
                $matrix = DocumentAiLayoutText::tableToMatrix($document, $table);
                $entry = [
                    'page_index' => $pageIndex,
                    'table_index' => $tIndex,
                    'rows' => $matrix,
                ];
                $pageTables[] = $entry;
                $tables[] = $entry;
            }

            $pageSummaries[] = [
                'page_index' => $pageIndex,
                'block_count' => count($blocks),
                'table_count' => count($pageTables),
                'blocks' => $blocks,
            ];
        }

        return new GoogleOcrResult(
            engineName: self::ENGINE,
            mimeType: 'application/pdf',
            rawText: $text,
            confidence: null,
            structuredBlocks: [
                'source' => self::ENGINE,
                'pages' => $pageSummaries,
                'lines' => $this->linesFromText($text),
            ],
            tables: $tables === [] ? null : $tables,
        );
    }

    /**
     * @return list<string>
     */
    private function linesFromText(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));
    }
}
