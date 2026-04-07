<?php

namespace App\Actions\Quotation;

use App\Models\IngestionBatch;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationReviewDraft;
use App\Models\User;
use App\Services\Quotation\QuotationApprovalProductLinker;
use App\Services\Supplier\SyncSupplierContactFromQuotation;
use App\Support\Quotation\QuotationTextNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveQuotationReviewAction
{
    public function __construct(
        private readonly QuotationApprovalProductLinker $quotationApprovalProductLinker,
        private readonly SyncSupplierContactFromQuotation $syncSupplierContactFromQuotation,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Same shape as review form (header + items + reviewer_notes)
     */
    public function execute(IngestionBatch $batch, User $user, array $payload): Quotation
    {
        if ($batch->quotation()->exists()) {
            throw new InvalidArgumentException(__('A quotation is already approved for this batch.'));
        }

        $ai = $batch->aiExtraction;
        if ($ai === null || ! is_array($ai->extraction_json)) {
            throw new InvalidArgumentException(__('AI extraction is missing; cannot approve.'));
        }

        $headerAi = is_array($ai->extraction_json['quotation_header'] ?? null)
            ? $ai->extraction_json['quotation_header']
            : [];
        $itemsAi = is_array($ai->extraction_json['items'] ?? null)
            ? array_values($ai->extraction_json['items'])
            : [];

        $linker = $this->quotationApprovalProductLinker;

        return DB::transaction(function () use ($batch, $user, $payload, $ai, $headerAi, $itemsAi, $linker): Quotation {
            $quoteDate = $payload['quote_date'] ?? null;
            $parsed = null;
            if ($quoteDate !== null && $quoteDate !== '') {
                try {
                    $parsed = Carbon::parse($quoteDate)->format('Y-m-d');
                } catch (\Throwable) {
                    $parsed = null;
                }
            }

            $quotation = Quotation::query()->create([
                'ingestion_batch_id' => $batch->id,
                'ai_extraction_id' => $ai->id,
                'supplier_id' => $batch->supplier_id,
                'supplier_name' => QuotationTextNormalizer::spacing((string) ($payload['supplier_name'] ?? '')),
                'supplier_quote_number' => QuotationTextNormalizer::spacing((string) ($payload['supplier_quote_number'] ?? '')),
                'quote_date' => $parsed,
                'contact_person' => QuotationTextNormalizer::spacing((string) ($payload['contact_person'] ?? '')),
                'notes' => QuotationTextNormalizer::nullableSpacing((string) ($payload['notes'] ?? '')),
                'currency' => (string) ($headerAi['currency'] ?? 'VND'),
                'subtotal_before_tax' => null,
                'tax_amount' => null,
                'total_amount' => $payload['total_amount'] ?? null,
                'header_snapshot_json' => $headerAi,
                'entry_source' => Quotation::ENTRY_SOURCE_AI_INGESTION,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $rows = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $snap = $itemsAi[$index] ?? null;
                $rawName = (string) ($row['raw_name'] ?? '');
                $rawNameRaw = is_array($snap) ? (string) ($snap['raw_name'] ?? '') : null;

                $item = QuotationItem::query()->create([
                    'quotation_id' => $quotation->id,
                    'line_no' => $index + 1,
                    'raw_name' => QuotationTextNormalizer::spacing($rawName),
                    'raw_name_raw' => $rawNameRaw !== '' ? $rawNameRaw : null,
                    'raw_model' => QuotationTextNormalizer::spacing((string) ($row['raw_model'] ?? '')),
                    'brand' => QuotationTextNormalizer::spacing((string) ($row['brand'] ?? '')),
                    'unit' => QuotationTextNormalizer::spacing((string) ($row['unit'] ?? '')),
                    'quantity' => $row['quantity'] ?? null,
                    'unit_price' => $row['unit_price'] ?? null,
                    'vat_percent' => $row['vat_percent'] ?? null,
                    'line_total' => $row['line_total'] ?? null,
                    'specs_text' => QuotationTextNormalizer::nullableSpacing((string) ($row['specs_text'] ?? '')),
                    'line_snapshot_json' => is_array($snap) ? $snap : null,
                ]);

                $linker->handle($item, $user);
            }

            QuotationReviewDraft::query()->updateOrCreate(
                ['ingestion_batch_id' => $batch->id],
                [
                    'ai_extraction_id' => $ai->id,
                    'payload_json' => $payload,
                    'review_status' => QuotationReviewDraft::STATUS_APPROVED,
                    'reviewer_notes' => QuotationTextNormalizer::nullableSpacing((string) ($payload['reviewer_notes'] ?? '')),
                    'last_edited_by' => $user->id,
                    'approved_quotation_id' => $quotation->id,
                ]
            );

            $batch->forceFill(['status' => 'approved'])->save();

            $this->syncSupplierContactFromQuotation->sync($quotation);

            return $quotation;
        });
    }
}
