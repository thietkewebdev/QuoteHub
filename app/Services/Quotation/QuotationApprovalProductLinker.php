<?php

namespace App\Services\Quotation;

use App\Actions\Quotation\SetQuotationItemProductMappingAction;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * After quotation approval: auto-link lines to existing active products when suggestion score is high enough.
 * If the catalog product has a {@see Product::$brand_id}, the line text must mention that brand (name/code/column/snapshot);
 * otherwise identical model/SKU codes from unrelated lines would link incorrectly.
 */
final class QuotationApprovalProductLinker
{
    /** Conservative procurement default: exact SKU (100) qualifies; alias 88/82 do not. */
    public const MIN_AUTO_LINK_SCORE = 90;

    public function __construct(
        private readonly ProductMappingSuggestionService $productMappingSuggestionService,
        private readonly SetQuotationItemProductMappingAction $setQuotationItemProductMappingAction,
    ) {}

    public function handle(QuotationItem $item, User $user): void
    {
        if ($item->mapped_product_id !== null) {
            Log::info('quotation.auto_link.skipped_already_mapped', [
                'quotation_item_id' => $item->id,
                'quotation_id' => $item->quotation_id,
            ]);

            return;
        }

        $suggestions = $this->productMappingSuggestionService->suggest($item);
        $top = $suggestions->first();

        if ($top === null) {
            Log::info('quotation.auto_link.no_suggestion', [
                'quotation_item_id' => $item->id,
                'quotation_id' => $item->quotation_id,
            ]);

            return;
        }

        Log::info('quotation.auto_link.suggestion_found', [
            'quotation_item_id' => $item->id,
            'quotation_id' => $item->quotation_id,
            'suggested_product_id' => $top->productId,
            'score' => $top->score,
        ]);

        if ($top->score < self::MIN_AUTO_LINK_SCORE) {
            Log::info('quotation.auto_link.skipped_low_score', [
                'quotation_item_id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'suggested_product_id' => $top->productId,
                'score' => $top->score,
                'min_score' => self::MIN_AUTO_LINK_SCORE,
            ]);

            return;
        }

        $product = Product::query()->with('brand')->find($top->productId);
        if ($product instanceof Product && ! $this->lineAppearsToMatchProductBrand($item, $product)) {
            Log::info('quotation.auto_link.skipped_brand_mismatch', [
                'quotation_item_id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'suggested_product_id' => $top->productId,
                'score' => $top->score,
                'product_brand_id' => $product->brand_id,
            ]);

            return;
        }

        $this->setQuotationItemProductMappingAction->execute($item, $user, $top->productId);

        Log::info('quotation.auto_link.applied', [
            'quotation_item_id' => $item->id,
            'quotation_id' => $item->quotation_id,
            'suggested_product_id' => $top->productId,
            'score' => $top->score,
        ]);
    }

    /**
     * When the catalog product has a brand, require the quotation line text (name, model, brand column, AI snapshot)
     * to mention that brand — otherwise SKU / alias matches alone can link unrelated supplier lines (same model code).
     */
    private function lineAppearsToMatchProductBrand(QuotationItem $item, Product $product): bool
    {
        if ($product->brand_id === null || $product->brand === null) {
            return true;
        }

        $haystack = self::quotationLineBrandHaystackLower($item);
        if ($haystack === '') {
            return false;
        }

        $brand = $product->brand;
        $name = mb_strtolower(trim((string) $brand->name), 'UTF-8');
        if ($name !== '' && str_contains($haystack, $name)) {
            return true;
        }

        $code = trim((string) ($brand->code ?? ''));
        $codeLower = $code !== '' ? mb_strtolower($code, 'UTF-8') : '';
        if ($codeLower !== '' && str_contains($haystack, $codeLower)) {
            return true;
        }

        $lineBrandCol = mb_strtolower(trim((string) ($item->brand ?? '')), 'UTF-8');
        if ($lineBrandCol !== '' && ($lineBrandCol === $name || ($codeLower !== '' && $lineBrandCol === $codeLower))) {
            return true;
        }

        return false;
    }

    private static function quotationLineBrandHaystackLower(QuotationItem $item): string
    {
        $snap = $item->line_snapshot_json;
        $snap = is_array($snap) ? $snap : [];

        $parts = [
            (string) ($item->raw_name ?? ''),
            (string) ($item->raw_name_raw ?? ''),
            (string) ($item->raw_model ?? ''),
            (string) ($item->brand ?? ''),
            (string) ($snap['raw_name'] ?? ''),
            (string) ($snap['raw_model'] ?? ''),
            (string) ($snap['brand'] ?? ''),
        ];

        $joined = trim(implode(' ', array_map(static fn (string $v): string => trim($v), $parts)));

        return $joined === '' ? '' : mb_strtolower($joined, 'UTF-8');
    }
}
