<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\QuotationItem;
use App\Services\Quotation\PriceHistoryQuery;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Fills the product create form from a visible approved quotation line (same scope as {@see PriceHistoryQuery}).
 */
final class ProductCreatePrefillFromQuotationLine
{
    /**
     * @param  Schema  $formSchema  Model-bound form container ({@see Component::getModelRootContainer()}).
     */
    public static function apply(Set $set, int $quotationItemId, Schema $formSchema): void
    {
        $item = PriceHistoryQuery::make()
            ->whereKey($quotationItemId)
            ->with(['quotation'])
            ->first();

        if (! $item instanceof QuotationItem) {
            return;
        }

        $formStatePath = $formSchema->getStatePath();
        $prefix = filled($formStatePath) ? $formStatePath : 'data';
        $p = static fn (string $field): string => "{$prefix}.{$field}";

        $partial = [];

        $name = trim((string) ($item->raw_name ?? ''));
        if ($name !== '') {
            $set($p('name'), $name, isAbsolute: true);
            $partial['name'] = $name;
        }

        $model = trim((string) ($item->raw_model ?? ''));
        $sku = $model !== '' ? $model : null;
        $set($p('sku'), $sku, isAbsolute: true);
        $partial['sku'] = $sku;

        $brandId = self::resolveBrandId($item);
        $brandState = $brandId !== null ? (string) $brandId : null;
        $set($p('brand_id'), $brandState, isAbsolute: true, shouldCallUpdatedHooks: true);
        $partial['brand_id'] = $brandState;

        $unit = trim((string) ($item->unit ?? ''));
        $uom = $unit !== '' ? $unit : null;
        $set($p('unit_of_measure'), $uom, isAbsolute: true);
        $partial['unit_of_measure'] = $uom;

        $specs = trim((string) ($item->specs_text ?? ''));
        if ($specs !== '') {
            $limited = Str::limit($specs, 65000, '…');
            $set($p('specs_text'), $limited, isAbsolute: true);
            $partial['specs_text'] = $limited;
        }

        $set($p('slug'), null, isAbsolute: true);
        $partial['slug'] = null;

        $formSchema->fillPartially(
            $partial,
            array_keys($partial),
            shouldFillStateWithNull: false,
        );
    }

    public static function formatLineLabel(QuotationItem $item): string
    {
        $item->loadMissing('quotation');

        $supplier = Str::limit(trim((string) ($item->quotation?->supplier_name ?? '')), 42, '…');
        $name = Str::limit(trim((string) ($item->raw_name ?? '')), 56, '…');
        if ($name === '') {
            $name = __('(no line text)');
        }

        $model = trim((string) ($item->raw_model ?? ''));
        $tail = $model !== '' ? ' · '.$model : '';

        if ($supplier !== '') {
            return $supplier.' — '.$name.$tail;
        }

        return $name.$tail;
    }

    /**
     * Match an existing catalog brand, or create one when the line clearly names a Latin brand code
     * (e.g. "TOA" in the product title) so prefill and the brand select both have a row to use.
     */
    public static function resolveBrandId(QuotationItem $item): ?int
    {
        $id = self::findExistingBrandMatch($item);
        if ($id !== null) {
            return $id;
        }

        return self::ensureBrandFromQuotationLine($item);
    }

    /**
     * Resolves {@see Brand} id from line text / column / snapshot only (no inserts).
     */
    private static function findExistingBrandMatch(QuotationItem $item): ?int
    {
        $fromColumn = trim((string) ($item->brand ?? ''));
        if ($fromColumn !== '') {
            $id = self::findActiveBrandIdByExactNameOrCode($fromColumn);
            if ($id !== null) {
                return $id;
            }
        }

        $haystack = self::brandResolutionHaystack($item, $fromColumn);

        if ($haystack === '') {
            return null;
        }

        foreach (self::haystackSearchTokens($haystack) as $token) {
            $id = self::findActiveBrandIdByExactNameOrCode($token);
            if ($id !== null) {
                return $id;
            }
        }

        $haystackLower = mb_strtolower($haystack, 'UTF-8');

        $bestId = null;
        $bestLen = 0;

        foreach (Brand::query()->where('is_active', true)->cursor() as $brand) {
            foreach ([(string) $brand->name, (string) ($brand->code ?? '')] as $fragment) {
                $n = trim($fragment);
                if (mb_strlen($n, 'UTF-8') < 2) {
                    continue;
                }
                $needle = mb_strtolower($n, 'UTF-8');
                if (! str_contains($haystackLower, $needle)) {
                    continue;
                }
                $len = mb_strlen($n, 'UTF-8');
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestId = (int) $brand->getKey();
                }
            }
        }

        return $bestId;
    }

    private static function ensureBrandFromQuotationLine(QuotationItem $item): ?int
    {
        $candidate = self::candidateBrandNameForAutoCreate($item);
        if ($candidate === null) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || mb_strlen($candidate, 'UTF-8') > 120) {
            return null;
        }

        $lower = mb_strtolower($candidate, 'UTF-8');

        $existing = Brand::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$lower])
            ->first();

        if ($existing !== null) {
            if (! $existing->is_active) {
                $existing->forceFill(['is_active' => true])->save();
            }

            return (int) $existing->getKey();
        }

        $brand = Brand::query()->create([
            'supplier_id' => null,
            'name' => $candidate,
            'slug' => self::uniqueBrandSlug($candidate),
            'code' => self::guessBrandCodeFromLabel($candidate),
            'is_active' => true,
        ]);

        return (int) $brand->getKey();
    }

    private static function candidateBrandNameForAutoCreate(QuotationItem $item): ?string
    {
        $fromColumn = trim((string) ($item->brand ?? ''));
        if ($fromColumn !== '') {
            return $fromColumn;
        }

        $snap = $item->line_snapshot_json;
        $snap = is_array($snap) ? $snap : [];
        $fromSnap = trim((string) ($snap['brand'] ?? ''));
        if ($fromSnap !== '') {
            return $fromSnap;
        }

        return self::inferLatinBrandLabelFromLine($item);
    }

    /**
     * Picks a standalone Latin token (e.g. TOA) from line text; avoids tokens glued to model codes like {@code SC-610M}.
     *
     * @return non-empty-string|null
     */
    private static function inferLatinBrandLabelFromLine(QuotationItem $item): ?string
    {
        $snap = is_array($item->line_snapshot_json) ? $item->line_snapshot_json : [];
        $chunks = array_filter([
            (string) ($item->raw_name ?? ''),
            (string) ($item->raw_model ?? ''),
            (string) ($snap['raw_name'] ?? ''),
            (string) ($snap['raw_model'] ?? ''),
        ]);
        $text = implode("\n", $chunks);
        if ($text === '') {
            return null;
        }

        preg_match_all('/(?<=[\s\-–—_,(\[{<]|^)([A-Z]{2,5})(?=\s|$)/', $text, $matches);
        $tokens = array_values(array_unique($matches[1] ?? []));
        if ($tokens === []) {
            return null;
        }

        $block = [
            'PCS' => true, 'SET' => true, 'BOX' => true, 'NEW' => true, 'VAT' => true,
            'USD' => true, 'VND' => true, 'MTR' => true, 'CTN' => true, 'AND' => true,
            'THE' => true, 'SKU' => true, 'OEM' => true, 'LED' => true, 'RGB' => true,
            'USB' => true, 'LAN' => true, 'POE' => true, 'IP' => true, 'TV' => true,
            'AC' => true, 'DC' => true, 'HF' => true, 'LF' => true, 'MF' => true,
        ];

        $candidates = [];
        foreach ($tokens as $t) {
            if (isset($block[$t])) {
                continue;
            }
            $candidates[] = $t;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidates[0];
    }

    private static function guessBrandCodeFromLabel(string $name): ?string
    {
        $t = strtoupper(trim($name));

        return preg_match('/^[A-Z0-9\-]{2,12}$/', $t) ? $t : null;
    }

    private static function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $n = 0;
        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    private static function findActiveBrandIdByExactNameOrCode(string $label): ?int
    {
        $lower = mb_strtolower(trim($label), 'UTF-8');
        if ($lower === '') {
            return null;
        }

        $id = Brand::query()
            ->where('is_active', true)
            ->where(function ($q) use ($lower): void {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$lower])
                    ->orWhere(function ($q2) use ($lower): void {
                        $q2->whereNotNull('code')
                            ->where('code', '!=', '')
                            ->whereRaw('LOWER(TRIM(code)) = ?', [$lower]);
                    });
            })
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return list<string>
     */
    private static function haystackSearchTokens(string $haystack): array
    {
        $haystack = trim($haystack);
        if ($haystack === '') {
            return [];
        }

        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-]*/u', $haystack, $matches);
        $unique = [];
        foreach ($matches[0] ?? [] as $raw) {
            $t = mb_strtolower(trim((string) $raw), 'UTF-8');
            if (mb_strlen($t, 'UTF-8') < 2) {
                continue;
            }
            $unique[$t] = $t;
        }

        $list = array_values($unique);
        usort($list, fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return $list;
    }

    /**
     * Text used to match catalog brands: columns plus AI snapshot / OCR raw line when present.
     */
    private static function brandResolutionHaystack(QuotationItem $item, string $fromColumn): string
    {
        $snap = $item->line_snapshot_json;
        $snap = is_array($snap) ? $snap : [];

        $parts = [
            (string) ($item->raw_name ?? ''),
            (string) ($item->raw_name_raw ?? ''),
            (string) ($item->raw_model ?? ''),
            $fromColumn,
            (string) ($snap['raw_name'] ?? ''),
            (string) ($snap['raw_model'] ?? ''),
            (string) ($snap['brand'] ?? ''),
        ];

        return trim(implode(' ', array_filter(array_map(
            static fn (string $v): string => trim($v),
            $parts,
        ))));
    }
}
