<?php

namespace App\Services\Quotation;

/**
 * One ranked suggestion for mapping a quotation line to a canonical product.
 */
final class ProductMappingSuggestion
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly int $productId,
        public readonly int $score,
        public readonly array $reasons,
        public readonly ?string $productName = null,
        public readonly ?string $productSku = null,
    ) {}

    public function dropdownLabel(): string
    {
        $name = $this->productName ?? '#'.$this->productId;
        $skuPart = $this->productSku !== null && $this->productSku !== '' ? ' ('.$this->productSku.')' : '';

        return '['.$this->score.'] '.$name.$skuPart.' — '.implode('; ', $this->reasons);
    }
}
