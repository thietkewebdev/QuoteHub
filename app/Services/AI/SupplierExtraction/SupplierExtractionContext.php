<?php

namespace App\Services\AI\SupplierExtraction;

use App\Models\SupplierExtractionProfile;
use App\Support\SupplierExtraction\SupplierProfileApplicationMode;

/**
 * Result of supplier profile resolution before OCR → AI extraction.
 */
final readonly class SupplierExtractionContext
{
    /**
     * @param  list<string>  $matchedTerms
     */
    public function __construct(
        public SupplierProfileApplicationMode $mode,
        public ?int $supplierId,
        public ?SupplierExtractionProfile $profile,
        public ?float $inferenceRawScore,
        /** Heuristic 0–1 score; confirmed catalog = 1.0, inferred from OCR = sub-unity, none = null */
        public ?float $supplierInferenceConfidence,
        public array $matchedTerms,
    ) {}

    /**
     * @return array{supplier_extraction_profile_id: int|null, supplier_profile_mode: string, supplier_profile_inference: array<string, mixed>|null}
     */
    public function persistencePayload(): array
    {
        $inference = match ($this->mode) {
            SupplierProfileApplicationMode::Confirmed => [
                'source' => 'confirmed_catalog',
                'supplier_id' => $this->supplierId,
                'inference_confidence' => $this->supplierInferenceConfidence,
            ],
            SupplierProfileApplicationMode::Inferred => [
                'source' => 'inferred_ocr',
                'supplier_id' => $this->supplierId,
                'score_raw' => $this->inferenceRawScore,
                'matched_terms' => $this->matchedTerms,
                'inference_confidence' => $this->supplierInferenceConfidence,
            ],
            default => null,
        };

        return [
            'supplier_extraction_profile_id' => $this->profile?->id,
            'supplier_profile_mode' => $this->mode->value,
            'supplier_profile_inference' => $inference,
        ];
    }
}
