<?php

namespace Tests\Feature;

use App\Models\IngestionBatch;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Quotation\PriceHistoryQuery;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_batch_delete_only_manual_approved_lines_remain(): void
    {
        $user = UserFactory::new()->create();

        $batch = IngestionBatch::query()->create([
            'source_channel' => 'email',
            'received_at' => now(),
            'uploaded_by' => $user->id,
            'status' => 'approved',
            'file_count' => 0,
        ]);

        $aiQuotation = Quotation::query()->create([
            'ingestion_batch_id' => $batch->id,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Supplier A',
            'supplier_quote_number' => 'Q-AI',
            'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        QuotationItem::query()->create([
            'quotation_id' => $aiQuotation->id,
            'line_no' => 1,
            'raw_name' => 'AI line',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $manualQuotation = Quotation::query()->create([
            'ingestion_batch_id' => null,
            'ai_extraction_id' => null,
            'supplier_id' => null,
            'supplier_name' => 'Supplier M',
            'supplier_quote_number' => 'Q-M',
            'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        QuotationItem::query()->create([
            'quotation_id' => $manualQuotation->id,
            'line_no' => 1,
            'raw_name' => 'Manual line',
            'quantity' => 1,
            'unit_price' => 200,
            'line_total' => 200,
        ]);

        $this->assertSame(2, (int) PriceHistoryQuery::make()->count());

        $batch->delete();

        $this->assertSame(1, (int) PriceHistoryQuery::make()->count());
        $this->assertSame('Manual line', (string) PriceHistoryQuery::make()->firstOrFail()->raw_name);
    }
}
