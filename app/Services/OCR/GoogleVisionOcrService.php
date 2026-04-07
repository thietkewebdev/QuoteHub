<?php

namespace App\Services\OCR;

use Google\ApiCore\ApiException;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Block\BlockType;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Paragraph;
use Google\Cloud\Vision\V1\Word;

/**
 * Google Cloud Vision — DOCUMENT_TEXT_DETECTION on a local image file.
 */
final class GoogleVisionOcrService
{
    public const PROVIDER = 'google_vision';

    /**
     * @return array{
     *     provider: string,
     *     full_text: string,
     *     pages: list<array{blocks: list<array<string, mixed>>}>
     * }
     *
     * @throws ApiException
     */
    public function detectDocumentText(string $absoluteImagePath): array
    {
        if (! is_readable($absoluteImagePath)) {
            throw new \InvalidArgumentException(__('Image path is not readable: :path', ['path' => $absoluteImagePath]));
        }

        $bytes = file_get_contents($absoluteImagePath);
        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException(__('Could not read image file.'));
        }

        $this->bootstrapCredentials();

        $feature = (new Feature)->setType(Type::DOCUMENT_TEXT_DETECTION);

        $request = (new AnnotateImageRequest)
            ->setImage((new Image)->setContent($bytes))
            ->setFeatures([$feature]);

        $batch = BatchAnnotateImagesRequest::build([$request]);

        $client = new ImageAnnotatorClient;
        try {
            $response = $client->batchAnnotateImages($batch);
        } finally {
            $client->close();
        }

        $responses = iterator_to_array($response->getResponses());
        $first = $responses[0] ?? null;
        if ($first === null) {
            return [
                'provider' => self::PROVIDER,
                'full_text' => '',
                'pages' => [],
            ];
        }

        if ($first->hasError()) {
            throw new \RuntimeException((string) $first->getError()->getMessage());
        }

        $annotation = $first->getFullTextAnnotation();
        if ($annotation === null) {
            return [
                'provider' => self::PROVIDER,
                'full_text' => '',
                'pages' => [],
            ];
        }

        $fullText = trim((string) $annotation->getText());
        $pagesOut = [];

        foreach ($annotation->getPages() as $page) {
            $blocksOut = [];
            foreach ($page->getBlocks() as $block) {
                $paragraphs = [];
                foreach ($block->getParagraphs() as $paragraph) {
                    $paragraphs[] = [
                        'text' => $this->paragraphText($paragraph),
                        'confidence' => round((float) $paragraph->getConfidence(), 6),
                    ];
                }
                $blocksOut[] = [
                    'block_type' => BlockType::name($block->getBlockType()),
                    'confidence' => round((float) $block->getConfidence(), 6),
                    'paragraphs' => $paragraphs,
                ];
            }
            $pagesOut[] = ['blocks' => $blocksOut];
        }

        return [
            'provider' => self::PROVIDER,
            'full_text' => $fullText,
            'pages' => $pagesOut,
        ];
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

    private function paragraphText(Paragraph $paragraph): string
    {
        $parts = [];
        foreach ($paragraph->getWords() as $word) {
            $parts[] = $this->wordText($word);
        }

        return trim(implode(' ', array_filter($parts, fn (string $s): bool => $s !== '')));
    }

    private function wordText(Word $word): string
    {
        $s = '';
        foreach ($word->getSymbols() as $symbol) {
            $s .= $symbol->getText();
        }

        return $s;
    }
}
