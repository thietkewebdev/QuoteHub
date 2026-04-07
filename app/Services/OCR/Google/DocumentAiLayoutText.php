<?php

namespace App\Services\OCR\Google;

use Google\Cloud\DocumentAI\V1\Document;
use Google\Cloud\DocumentAI\V1\Document\Page\Layout;
use Google\Cloud\DocumentAI\V1\Document\Page\Table;
use Google\Cloud\DocumentAI\V1\Document\Page\Table\TableRow;

/**
 * Resolves text spans from Document AI layouts into plain strings.
 */
final class DocumentAiLayoutText
{
    public static function fromLayout(string $documentText, ?Layout $layout): string
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
    public static function tableToMatrix(Document $document, Table $table): array
    {
        $text = (string) $document->getText();
        $rows = [];

        foreach ($table->getHeaderRows() as $row) {
            $rows[] = self::cellsToStrings($text, $row);
        }
        foreach ($table->getBodyRows() as $row) {
            $rows[] = self::cellsToStrings($text, $row);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function cellsToStrings(string $documentText, TableRow $row): array
    {
        $cells = [];
        foreach ($row->getCells() as $cell) {
            $layout = $cell->hasLayout() ? $cell->getLayout() : null;
            $cells[] = self::fromLayout($documentText, $layout);
        }

        return $cells;
    }
}
