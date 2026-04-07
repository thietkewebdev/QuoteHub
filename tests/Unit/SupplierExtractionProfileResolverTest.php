<?php

namespace Tests\Unit;

use App\Models\IngestionBatch;
use App\Models\Supplier;
use App\Models\SupplierExtractionProfile;
use App\Services\AI\SupplierExtraction\SupplierExtractionProfileResolver;
use App\Support\SupplierExtraction\SupplierProfileApplicationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierExtractionProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_when_batch_has_catalog_supplier(): void
    {
        config(['quotation_ai.supplier_inference.enabled' => true]);

        $supplier = Supplier::query()->create([
            'name' => 'ACME Việt Nam',
            'is_active' => true,
        ]);

        SupplierExtractionProfile::query()->create([
            'supplier_id' => $supplier->id,
            'is_enabled' => true,
            'hints' => [
                'keyword_aliases' => ['ACME VN'],
            ],
        ]);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'supplier_id' => $supplier->id,
            'received_at' => now(),
            'status' => 'ocr_done',
        ]);

        $resolver = app(SupplierExtractionProfileResolver::class);
        $ctx = $resolver->resolve($batch, 'Random OCR without ACME');

        $this->assertSame(SupplierProfileApplicationMode::Confirmed, $ctx->mode);
        $this->assertSame($supplier->id, $ctx->supplierId);
        $this->assertNotNull($ctx->profile);
        $this->assertNull($ctx->inferenceRawScore);
    }

    public function test_inferred_when_alias_matches_ocr(): void
    {
        config([
            'quotation_ai.supplier_inference.enabled' => true,
            'quotation_ai.supplier_inference.min_score' => 2.0,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Công ty XYZ',
            'is_active' => true,
        ]);

        SupplierExtractionProfile::query()->create([
            'supplier_id' => $supplier->id,
            'is_enabled' => true,
            'hints' => [
                'keyword_aliases' => ['XYZ Trading'],
            ],
        ]);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'ocr_done',
        ]);

        $resolver = app(SupplierExtractionProfileResolver::class);
        $ctx = $resolver->resolve($batch, 'BÁO GIÁ từ XYZ Trading cho dự án');

        $this->assertSame(SupplierProfileApplicationMode::Inferred, $ctx->mode);
        $this->assertSame($supplier->id, $ctx->supplierId);
        $this->assertNotNull($ctx->profile);
        $this->assertNotNull($ctx->inferenceRawScore);
        $this->assertContains('XYZ Trading', $ctx->matchedTerms);
    }

    public function test_none_when_inference_disabled(): void
    {
        config(['quotation_ai.supplier_inference.enabled' => false]);

        $supplier = Supplier::query()->create([
            'name' => 'Only Alias Co',
            'is_active' => true,
        ]);

        SupplierExtractionProfile::query()->create([
            'supplier_id' => $supplier->id,
            'is_enabled' => true,
            'hints' => [
                'keyword_aliases' => ['ONLY_ALIAS_MARKER'],
            ],
        ]);

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'ocr_done',
        ]);

        $resolver = app(SupplierExtractionProfileResolver::class);
        $ctx = $resolver->resolve($batch, 'ONLY_ALIAS_MARKER in OCR');

        $this->assertSame(SupplierProfileApplicationMode::None, $ctx->mode);
        $this->assertNull($ctx->supplierId);
    }
}
