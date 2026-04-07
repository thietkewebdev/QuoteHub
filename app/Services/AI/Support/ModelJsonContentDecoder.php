<?php

namespace App\Services\AI\Support;

use JsonException;

/**
 * Decodes JSON from LLM chat message content (strips optional markdown code fences).
 */
final class ModelJsonContentDecoder
{
    /**
     * @return array<string, mixed>
     */
    public static function decodeObject(string $content): array
    {
        $trimmed = trim($content);

        if (preg_match('/^```(?:json)?\s*\R(.*?)\R```$/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        } elseif (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \InvalidArgumentException(
                'Model response is not valid JSON: '.$e->getMessage(),
                previous: $e
            );
        }

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Model JSON root must be an object.');
        }

        return $decoded;
    }
}
