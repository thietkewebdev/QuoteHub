<?php

namespace App\Services\OCR\Google;

use Google\ApiCore\ApiException;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Block\BlockType;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Paragraph;
use Google\Cloud\Vision\V1\TextAnnotation;
use Google\Cloud\Vision\V1\Word;

/**
 * Google Cloud Vision — DOCUMENT_TEXT_DETECTION for raster images (no LLM).
 */
final class GoogleVisionOcrService
{
    public const ENGINE = 'google-cloud-vision';

    /**
     * @throws GoogleOcrException
     */
    public function extractImage(string $absolutePath, string $mimeType): GoogleOcrResult
    {
        if (! is_readable($absolutePath)) {
            throw new GoogleOcrException(__('Image file is not readable: :path', ['path' => $absolutePath]));
        }

        $content = file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            throw new GoogleOcrException(__('Could not read image bytes.'));
        }

        GoogleCredentialsBootstrap::apply();

        $feature = (new Feature)->setType(Type::DOCUMENT_TEXT_DETECTION);
        $model = trim((string) config('google_ocr.vision.model', ''));
        if ($model !== '') {
            $feature->setModel($model);
        }

        $imageRequest = (new AnnotateImageRequest)
            ->setImage((new Image)->setContent($content))
            ->setFeatures([$feature]);

        $batch = BatchAnnotateImagesRequest::build([$imageRequest]);

        try {
            $client = new ImageAnnotatorClient;
            $response = $client->batchAnnotateImages($batch);
            $client->close();
        } catch (ApiException $e) {
            throw new GoogleOcrException('Vision API: '.$e->getMessage(), (int) $e->getCode(), $e);
        }

        $responses = iterator_to_array($response->getResponses());
        $first = $responses[0] ?? null;
        if ($first === null) {
            throw new GoogleOcrException(__('Vision returned no annotation responses.'));
        }

        if ($first->hasError()) {
            $err = $first->getError();

            throw new GoogleOcrException((string) $err->getMessage());
        }

        $annotation = $first->getFullTextAnnotation();
        if ($annotation === null) {
            return new GoogleOcrResult(
                engineName: self::ENGINE,
                mimeType: $mimeType,
                rawText: '',
                confidence: null,
                structuredBlocks: [
                    'source' => self::ENGINE,
                    'blocks' => [],
                    'lines' => [],
                ],
                tables: null,
            );
        }

        $rawText = trim((string) $annotation->getText());
        [$blocks, $confidences] = $this->serializeBlocks($annotation);
        $confidence = $confidences === [] ? null : round(array_sum($confidences) / count($confidences), 6);

        return new GoogleOcrResult(
            engineName: self::ENGINE,
            mimeType: $mimeType,
            rawText: $rawText,
            confidence: $confidence,
            structuredBlocks: [
                'source' => self::ENGINE,
                'blocks' => $blocks,
                'lines' => $this->linesFromText($rawText),
            ],
            tables: null,
        );
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<float>}
     */
    private function serializeBlocks(TextAnnotation $annotation): array
    {
        $blocksOut = [];
        $confidences = [];

        foreach ($annotation->getPages() as $page) {
            foreach ($page->getBlocks() as $block) {
                $paragraphs = [];
                foreach ($block->getParagraphs() as $paragraph) {
                    $paragraphs[] = [
                        'text' => $this->paragraphText($paragraph),
                        'confidence' => $paragraph->getConfidence(),
                    ];
                    if ($paragraph->getConfidence() > 0) {
                        $confidences[] = (float) $paragraph->getConfidence();
                    }
                }
                if ($block->getConfidence() > 0) {
                    $confidences[] = (float) $block->getConfidence();
                }
                $blocksOut[] = [
                    'block_type' => BlockType::name($block->getBlockType()),
                    'confidence' => $block->getConfidence(),
                    'paragraphs' => $paragraphs,
                ];
            }
        }

        return [$blocksOut, $confidences];
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

    /**
     * @return list<string>
     */
    private function linesFromText(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));
    }
}
