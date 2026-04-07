<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Removes quotations whose ingestion_batch_id points at a missing row (e.g. FK checks were off during a delete).
 */
class PruneQuotationsMissingIngestionBatchCommand extends Command
{
    protected $signature = 'quotehub:prune-quotations-missing-batch
                            {--force : Delete matching quotations (default is list-only dry run)}';

    protected $description = 'List or delete approved/unapproved quotations that reference a non-existent ingestion batch';

    public function handle(): int
    {
        $query = $this->orphanQuery();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No quotations reference a missing ingestion batch.');

            return self::SUCCESS;
        }

        $this->warn("Found {$count} quotation(s) with ingestion_batch_id pointing to a missing batch.");
        foreach ((clone $query)->orderBy('id')->cursor() as $quotation) {
            /** @var Quotation $quotation */
            $this->line(sprintf(
                '  #%d  batch_id=%s  supplier=%s  approved=%s',
                $quotation->id,
                (string) $quotation->ingestion_batch_id,
                mb_substr((string) $quotation->supplier_name, 0, 60),
                $quotation->approved_at !== null ? 'yes' : 'no',
            ));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Dry run. Re-run with --force to delete these quotations and their line items.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Permanently delete these quotations?', false)) {
            return self::FAILURE;
        }

        $ids = (clone $query)->pluck('id')->all();
        DB::transaction(function () use ($ids): void {
            Quotation::query()->whereIn('id', $ids)->each(fn (Quotation $q) => $q->delete());
        });

        $this->info('Deleted '.count($ids).' quotation(s).');

        return self::SUCCESS;
    }

    /**
     * @return Builder<Quotation>
     */
    private function orphanQuery(): Builder
    {
        return Quotation::query()
            ->whereNotNull('ingestion_batch_id')
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('ingestion_batches')
                    ->whereColumn('ingestion_batches.id', 'quotations.ingestion_batch_id');
            });
    }
}
