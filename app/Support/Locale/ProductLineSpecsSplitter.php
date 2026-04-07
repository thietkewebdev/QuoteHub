<?php

namespace App\Support\Locale;

/**
 * Moves obvious technical tail from raw_name into specs_text (deterministic, before LLM).
 */
final class ProductLineSpecsSplitter
{
    /**
     * @return array{0: string, 1: string} [raw_name, specs_text]
     */
    public static function split(string $rawName, string $specsText, int $minNameLength = 80): array
    {
        $rawName = trim($rawName);
        $specsText = trim($specsText);
        if (mb_strlen($rawName) < $minNameLength) {
            return [$rawName, $specsText];
        }

        if ($specsText !== '' && mb_strlen($specsText) > 40) {
            return [$rawName, $specsText];
        }

        // Typical: "…(USB+LAN) Độ phân giải …" — keep title through closing paren, move specs after.
        if (preg_match(
            '/^(?P<head>.*?\))\s+(?P<tail>(?:Độ\s+phân|Độphân|Tốc\s+độ|Tốcđộ|\d+\s*dpi|dpi|[Mm]ã\s+vạch|[Mm]ãvạch|[Hh]ỗ\s+trợ|[Hh]ỗtrợ).+)$/us',
            $rawName,
            $m
        ) !== 1) {
            return [$rawName, $specsText];
        }

        $head = trim($m['head']);
        $tail = trim($m['tail']);
        if ($tail === '' || mb_strlen($head) < 25) {
            return [$rawName, $specsText];
        }

        $mergedSpecs = $specsText === '' ? $tail : $tail."\n".$specsText;

        return [$head, trim($mergedSpecs)];
    }
}
