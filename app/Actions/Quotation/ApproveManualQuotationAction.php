<?php

namespace App\Actions\Quotation;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationReviewDraft;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Quotation\ManualQuotationPayloadEnricher;
use App\Services\Quotation\QuotationApprovalProductLinker;
use App\Services\Supplier\SyncSupplierContactFromQuotation;
use App\Support\Quotation\QuotationTextNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveManualQuotationAction
{
    public function __construct(
        private readonly ManualQuotationPayloadEnricher $enricher,
        private readonly QuotationApprovalProductLinker $quotationApprovalProductLinker,
        private readonly SyncSupplierContactFromQuotation $syncSupplierContactFromQuotation,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(QuotationReviewDraft $draft, User $user, array $payload): Quotation
    {
        if ($draft->ingestion_batch_id !== null) {
            throw new InvalidArgumentException(__('This draft is not a manual entry draft.'));
        }

        if ($draft->approved_quotation_id !== null) {
            throw new InvalidArgumentException(__('A quotation is already approved for this draft.'));
        }

        $payload = $this->enricher->enrich($payload);

        $rows = array_values(is_array($payload['items'] ?? null) ? $payload['items'] : []);
        $hasLine = false;
        foreach ($rows as $row) {
            if (is_array($row) && trim((string) ($row['raw_name'] ?? '')) !== '') {
                $hasLine = true;
                break;
            }
        }
        if (! $hasLine) {
            throw new InvalidArgumentException(__('Add at least one line item with a product name.'));
        }

        $supplierId = isset($payload['supplier_id']) && $payload['supplier_id'] !== '' && $payload['supplier_id'] !== null
            ? (int) $payload['supplier_id']
            : null;
        $supplierName = QuotationTextNormalizer::spacing((string) ($payload['supplier_name'] ?? ''));
        if ($supplierId !== null) {
            $supplier = Supplier::query()->find($supplierId);
            if ($supplier !== null && trim($supplierName) === '') {
                $supplierName = QuotationTextNormalizer::spacing((string) $supplier->name);
            }
        }

        if (trim($supplierName) === '') {
            throw new InvalidArgumentException(__('Enter a supplier name, or select a supplier from the catalog.'));
        }

        $quoteDate = $payload['quote_date'] ?? null;
        $parsed = null;
        if ($quoteDate !== null && $quoteDate !== '') {
            try {
                $parsed = Carbon::parse($quoteDate)->format('Y-m-d');
            } catch (\Throwable) {
                $parsed = null;
            }
        }

        $headerSnapshot = [
            'source' => Quotation::ENTRY_SOURCE_MANUAL,
            'supplier_id' => $supplierId,
        ];

        $linker = $this->quotationApprovalProductLinker;

        return DB::transaction(function () use ($draft, $user, $payload, $supplierId, $supplierName, $parsed, $headerSnapshot, $rows, $linker): Quotation {
            $quotation = Quotation::query()->create([
                'ingestion_batch_id' => null,
                'ai_extraction_id' => null,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'supplier_quote_number' => QuotationTextNormalizer::spacing((string) ($payload['supplier_quote_number'] ?? '')),
                'quote_date' => $parsed,
                'contact_person' => QuotationTextNormalizer::spacing((string) ($payload['contact_person'] ?? '')),
                'notes' => QuotationTextNormalizer::nullableSpacing((string) ($payload['notes'] ?? '')),
                'currency' => 'VND',
                'subtotal_before_tax' => null,
                'tax_amount' => null,
                'total_amount' => $payload['total_amount'] ?? null,
                'header_snapshot_json' => $headerSnapshot,
                'entry_source' => Quotation::ENTRY_SOURCE_MANUAL,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $lineNo = 1;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (trim((string) ($row['raw_name'] ?? '')) === '') {
                    continue;
                }
                $mappedId = isset($row['mapped_product_id']) && $row['mapped_product_id'] !== '' && $row['mapped_product_id'] !== null
                    ? (int) $row['mapped_product_id']
                    : null;
                if ($mappedId <= 0) {
                    $mappedId = null;
                }
                $item = QuotationItem::query()->create([
                    'quotation_id' => $quotation->id,
                    'line_no' => $lineNo,
                    'raw_name' => QuotationTextNormalizer::spacing((string) ($row['raw_name'] ?? '')),
                    'raw_name_raw' => null,
                    'raw_model' => QuotationTextNormalizer::spacing((string) ($row['raw_model'] ?? '')),
                    'brand' => QuotationTextNormalizer::spacing((string) ($row['brand'] ?? '')),
                    'unit' => QuotationTextNormalizer::spacing((string) ($row['unit'] ?? '')),
                    'quantity' => $row['quantity'] ?? null,
                    'unit_price' => $row['unit_price'] ?? null,
                    'vat_percent' => $row['vat_percent'] ?? null,
                    'line_total' => $row['line_total'] ?? null,
                    'specs_text' => QuotationTextNormalizer::nullableSpacing((string) ($row['specs_text'] ?? '')),
                    'line_snapshot_json' => null,
                    'mapped_product_id' => $mappedId,
                    'mapped_at' => null,
                    'mapped_by' => null,
                ]);

                $linker->handle($item, $user);

                $lineNo++;
            }

            $draft->forceFill([
                'payload_json' => $payload,
                'review_status' => QuotationReviewDraft::STATUS_APPROVED,
                'reviewer_notes' => QuotationTextNormalizer::nullableSpacing((string) ($payload['reviewer_notes'] ?? '')),
                'last_edited_by' => $user->id,
                'approved_quotation_id' => $quotation->id,
                'ai_extraction_id' => null,
            ])->save();

            $this->syncSupplierContactFromQuotation->sync($quotation);

            return $quotation;
        });
    }
}
