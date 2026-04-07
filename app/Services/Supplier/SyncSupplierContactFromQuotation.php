<?php

declare(strict_types=1);

namespace App\Services\Supplier;

use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Support\Supplier\QuotationContactPersonParser;

/**
 * When a quotation is approved (or later linked to a catalog supplier), upsert a contact row from {@see Quotation::$contact_person}.
 */
final class SyncSupplierContactFromQuotation
{
    public function sync(Quotation $quotation): void
    {
        $supplierId = $quotation->supplier_id;
        $raw = trim((string) $quotation->contact_person);
        if ($supplierId === null || $raw === '') {
            return;
        }

        $supplier = Supplier::query()->with('contacts')->find($supplierId);
        if ($supplier === null) {
            return;
        }

        $parsed = QuotationContactPersonParser::parse($raw);
        if ($parsed === null) {
            return;
        }

        $name = trim($parsed['name']);
        if ($name === '') {
            return;
        }

        $phone = $parsed['phone'] ?? null;
        $phoneDigits = $phone !== null ? (preg_replace('/\D+/', '', $phone) ?? '') : '';

        foreach ($supplier->contacts as $existing) {
            if (mb_strtolower(trim((string) $existing->name)) !== mb_strtolower($name)) {
                continue;
            }
            $existingDigits = preg_replace('/\D+/', '', (string) $existing->phone) ?? '';
            if ($existingDigits === $phoneDigits) {
                return;
            }
        }

        $isFirst = $supplier->contacts->isEmpty();

        $maxOrder = (int) ($supplier->contacts->max('sort_order') ?? 0);

        SupplierContact::query()->create([
            'supplier_id' => (int) $supplier->getKey(),
            'name' => $name,
            'phone' => $phone,
            'notes' => __('From quotation #:id', ['id' => $quotation->getKey()]),
            'is_primary' => $isFirst,
            'sort_order' => $maxOrder + 1,
        ]);
    }
}
