<?php

namespace App\Actions\Supplier;

use App\Models\Quotation;
use App\Models\Supplier;
use App\Services\Supplier\SupplierRegistryService;
use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Ensures supplier master rows exist for every distinct supplier_name seen on approved quotations.
 * Does not change quotation rows (including supplier_name text).
 */
final class SyncSuppliersFromApprovedQuotationsAction
{
    public function __construct(
        private readonly SupplierRegistryService $registry,
    ) {}

    /**
     * @return array{created: int, already_existed: int, distinct_names: int}
     */
    public function execute(): array
    {
        $names = Quotation::query()
            ->whereNotNull('approved_at')
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->groupBy('supplier_name')
            ->pluck('supplier_name');

        $created = 0;
        $alreadyExisted = 0;

        DB::transaction(function () use ($names, &$created, &$alreadyExisted): void {
            foreach ($names as $rawName) {
                $normalized = SupplierNameNormalizer::normalize((string) $rawName);
                if ($normalized === '') {
                    continue;
                }

                if (Supplier::query()->where('normalized_name', $normalized)->exists()) {
                    $alreadyExisted++;

                    continue;
                }

                $this->registry->findOrCreateByDisplayName((string) $rawName);
                $created++;
            }
        });

        return [
            'created' => $created,
            'already_existed' => $alreadyExisted,
            'distinct_names' => $names->count(),
        ];
    }
}
