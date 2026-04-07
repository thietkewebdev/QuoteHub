<?php

namespace App\Actions\Quotation;

use App\Models\Quotation;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Services\Quotation\ManualQuotationPayloadEnricher;
use App\Services\Quotation\QuotationReviewPayloadFactory;
use App\Support\Quotation\QuotationTextNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a manual-entry {@see QuotationReviewDraft} from an approved quotation (header + lines + product map ids).
 * Does not copy approval metadata, batch, or AI extraction — those stay null until a new quotation is approved.
 */
class CloneQuotationToManualDraftAction
{
    public function __construct(
        private readonly QuotationReviewPayloadFactory $payloadFactory,
        private readonly ManualQuotationPayloadEnricher $enricher,
    ) {}

    public function execute(Quotation $source, ?User $user): QuotationReviewDraft
    {
        if ($source->approved_at === null) {
            throw new InvalidArgumentException(__('Only approved quotations can be cloned into a manual draft.'));
        }

        $source->load(['items' => fn ($q) => $q->orderBy('line_no')]);

        $payload = $this->payloadFactory->emptyPayload();
        $payload['supplier_id'] = $source->supplier_id;
        $payload['supplier_name'] = QuotationTextNormalizer::spacing((string) $source->supplier_name);
        $payload['supplier_quote_number'] = QuotationTextNormalizer::spacing((string) $source->supplier_quote_number);
        $payload['quote_date'] = $source->quote_date?->format('Y-m-d');
        $payload['contact_person'] = QuotationTextNormalizer::spacing((string) $source->contact_person);
        $payload['notes'] = QuotationTextNormalizer::nullableSpacing((string) ($source->notes ?? '')) ?? '';
        $payload['total_amount'] = $source->total_amount;
        $payload['reviewer_notes'] = '';
        $payload['cloned_from_quotation_id'] = $source->id;

        $items = [];
        foreach ($source->items as $line) {
            $items[] = [
                'raw_name' => QuotationTextNormalizer::spacing((string) $line->raw_name),
                'raw_model' => QuotationTextNormalizer::spacing((string) $line->raw_model),
                'brand' => QuotationTextNormalizer::spacing((string) $line->brand),
                'unit' => QuotationTextNormalizer::spacing((string) $line->unit),
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_percent' => $line->vat_percent,
                'line_total' => $line->line_total,
                'specs_text' => QuotationTextNormalizer::nullableSpacing((string) ($line->specs_text ?? '')) ?? '',
                'mapped_product_id' => $line->mapped_product_id,
            ];
        }
        $payload['items'] = $items;
        $payload = $this->enricher->enrich($payload);

        return DB::transaction(function () use ($user, $payload): QuotationReviewDraft {
            return QuotationReviewDraft::query()->create([
                'ingestion_batch_id' => null,
                'ai_extraction_id' => null,
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_DRAFT,
                'last_edited_by' => $user?->id,
            ]);
        });
    }
}
