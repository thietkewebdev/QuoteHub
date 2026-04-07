<?php

namespace App\Services\OCR\Google;

/**
 * Normalized OCR output from Google Vision or Document AI (no quotation mapping).
 */
final readonly class GoogleOcrResult
{
    /**
     * @param  array<string, mixed>  $structuredBlocks
     * @param  list<array<string, mixed>>|null  $tables
     */
    public function __construct(
        public string $engineName,
        public string $mimeType,
        public string $rawText,
        public ?float $confidence,
        public array $structuredBlocks,
        public ?array $tables,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        return [
            'engine' => $this->engineName,
            'mime_type' => $this->mimeType,
            'raw_text_length' => mb_strlen($this->rawText),
            'raw_text_preview' => mb_substr($this->rawText, 0, 500),
            'confidence' => $this->confidence,
            'structured_blocks' => $this->structuredBlocks,
            'tables' => $this->tables,
        ];
    }
}
