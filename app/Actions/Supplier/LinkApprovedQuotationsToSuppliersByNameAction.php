<?php

namespace App\Actions\Supplier;

use App\Models\Quotation;
use App\Models\Supplier;
use App\Services\Supplier\SyncSupplierContactFromQuotation;
use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Sets quotation.supplier_id where it is null, using normalized supplier_name → suppliers.normalized_name.
 * Never modifies supplier_name or line items (approved text stays as stored).
 */
final class LinkApprovedQuotationsToSuppliersByNameAction
{
    public function __construct(
        private readonly SyncSupplierContactFromQuotation $syncSupplierContactFromQuotation,
    ) {}

    /**
     * @return array{updated: int, skipped_no_match: int, examined: int}
     */
    public function execute(): array
    {
        $updated = 0;
        $skipped = 0;
        $examined = 0;

        DB::transaction(function () use (&$updated, &$skipped, &$examined): void {
            Quotation::query()
                ->whereNotNull('approved_at')
                ->whereNull('supplier_id')
                ->whereNotNull('supplier_name')
                ->where('supplier_name', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($quotations) use (&$updated, &$skipped, &$examined): void {
                    foreach ($quotations as $quotation) {
                        $examined++;
                        $normalized = SupplierNameNormalizer::normalize((string) $quotation->supplier_name);
                        if ($normalized === '') {
                            $skipped++;

                            continue;
                        }

                        $supplierId = Supplier::query()
                            ->where('normalized_name', $normalized)
                            ->value('id');

                        if ($supplierId === null) {
                            $skipped++;

                            continue;
                        }

                        $quotation->forceFill(['supplier_id' => $supplierId])->saveQuietly();
                        $this->syncSupplierContactFromQuotation->sync($quotation);
                        $updated++;
                    }
                });
        });

        return [
            'updated' => $updated,
            'skipped_no_match' => $skipped,
            'examined' => $examined,
        ];
    }
}
