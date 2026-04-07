<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quick supplier lookup on the operations dashboard (catalog rows, not raw quotation spellings).
 */
final class DashboardSupplierSearch
{
    private const DEFAULT_LIST_LIMIT = 100;

    private const SEARCH_RESULT_LIMIT = 50;

    /**
     * @return Collection<int, Supplier>
     */
    public function search(?string $query): Collection
    {
        $trimmed = $query !== null ? trim($query) : '';

        $q = Supplier::query()
            ->where('is_active', true)
            ->withCount([
                'quotations as approved_quotations_count' => fn (Builder $sub): Builder => $sub->whereNotNull('approved_at'),
            ]);

        if ($trimmed !== '') {
            $escaped = addcslashes($trimmed, '%_\\');
            $like = '%'.$escaped.'%';
            $q->where(function (Builder $w) use ($like): void {
                $w->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });

            return $q->orderBy('name')
                ->limit(self::SEARCH_RESULT_LIMIT)
                ->get();
        }

        return $q->orderBy('name')
            ->limit(self::DEFAULT_LIST_LIMIT)
            ->get();
    }
}
