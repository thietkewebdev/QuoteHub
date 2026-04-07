<?php

namespace Tests\Feature;

use App\Actions\Quotation\ApproveQuotationReviewAction;
use App\Models\AiExtraction;
use App\Models\IngestionBatch;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveQuotationReviewActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_triggers_auto_link_after_item_create_when_sku_matches(): void
    {
        $user = User::factory()->create();
        $batch = IngestionBatch::query()->create([
            'source_channel' => 'test',
            'received_at' => now(),
            'status' => 'review_pending',
        ]);

        $extractionJson = [
            'quotation_header' => ['currency' => 'VND'],
            'items' => [
                [
                    'raw_name' => 'Widget',
                    'raw_model' => 'CAT-SKU-AI',
                    'brand' => '',
                    'unit' => 'cái',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'vat_percent' => null,
                    'line_total' => 100,
                    'specs_text' => '',
                ],
            ],
        ];

        AiExtraction::query()->create([
            'ingestion_batch_id' => $batch->id,
            'model_name' => 'test',
            'prompt_version' => 'v1',
            'extraction_json' => $extractionJson,
            'confidence_overall' => 0.9,
            'warnings' => null,
        ]);

        $product = Product::query()->create([
            'supplier_id' => null,
            'brand_id' => null,
            'product_category_id' => null,
            'sku' => 'CAT-SKU-AI',
            'name' => 'Catalog',
            'slug' => 'cat-sku-ai',
            'is_active' => true,
        ]);

        $payload = [
            'supplier_name' => 'Supplier',
            'supplier_quote_number' => 'Q1',
            'quote_date' => '2026-04-05',
            'contact_person' => '',
            'notes' => '',
            'total_amount' => 100,
            'reviewer_notes' => '',
            'items' => [
                [
                    'raw_name' => 'Widget',
                    'raw_model' => 'CAT-SKU-AI',
                    'brand' => '',
                    'unit' => 'cái',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'vat_percent' => null,
                    'line_total' => 100,
                    'specs_text' => '',
                ],
            ],
        ];

        $quotation = app(ApproveQuotationReviewAction::class)->execute($batch, $user, $payload);

        $this->assertInstanceOf(Quotation::class, $quotation);
        $this->assertCount(1, $quotation->items);
        $line = $quotation->items->first();
        $this->assertSame($product->id, (int) $line->mapped_product_id);
    }
}
