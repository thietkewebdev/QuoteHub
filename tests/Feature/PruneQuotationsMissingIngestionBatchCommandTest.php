<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneQuotationsMissingIngestionBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_clean_database(): void
    {
        $this->artisan('quotehub:prune-quotations-missing-batch')
            ->assertSuccessful()
            ->expectsOutput('No quotations reference a missing ingestion batch.');
    }
}
