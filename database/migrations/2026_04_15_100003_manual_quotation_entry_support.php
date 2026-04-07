<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dropUniqueIndexOnColumn(string $table, string $column): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false)
                && ! ($index['primary'] ?? false)
                && ($index['columns'] ?? []) === [$column]) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index['name']);
                });
                break;
            }
        }
    }

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->upSqlite();

            return;
        }

        $this->dropUniqueIndexOnColumn('quotation_review_drafts', 'ingestion_batch_id');

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->dropForeign(['ingestion_batch_id']);
        });

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('ingestion_batch_id')->nullable()->change();
        });

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->foreign('ingestion_batch_id')
                ->references('id')
                ->on('ingestion_batches')
                ->cascadeOnDelete();
        });

        $this->dropUniqueIndexOnColumn('quotations', 'ingestion_batch_id');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['ingestion_batch_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('ingestion_batch_id')->nullable()->change();
            $table->string('entry_source', 32)->default('ai_ingestion');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('ingestion_batch_id')
                ->references('id')
                ->on('ingestion_batches')
                ->cascadeOnDelete();
            $table->index('entry_source');
        });
    }

    /**
     * SQLite cannot reliably rebuild tables via {@see Blueprint::change()} when JSON + FK graphs are involved.
     * On migrate:fresh there is no quotation data yet; we recreate affected tables in dependency order.
     */
    private function upSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('quotation_review_drafts');

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
        });

        Schema::dropIfExists('quotations');

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name', 512)->default('');
            $table->string('supplier_quote_number', 128)->default('');
            $table->date('quote_date')->nullable();
            $table->string('contact_person', 255)->default('');
            $table->text('notes')->nullable();
            $table->string('currency', 8)->default('VND');
            $table->decimal('subtotal_before_tax', 18, 4)->nullable();
            $table->decimal('tax_amount', 18, 4)->nullable();
            $table->decimal('total_amount', 18, 4)->nullable();
            $table->json('header_snapshot_json')->nullable();
            $table->string('entry_source', 32)->default('ai_ingestion');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('entry_source');
            $table->index(['ai_extraction_id', 'approved_at']);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
        });

        Schema::create('quotation_review_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload_json');
            $table->string('review_status', 32)->default('draft');
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->timestamps();

            $table->unique('ingestion_batch_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Non-trivial to reverse without data loss; keep forward-only on SQLite tests.
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['entry_source']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['ingestion_batch_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('entry_source');
            $table->unsignedBigInteger('ingestion_batch_id')->nullable(false)->change();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('ingestion_batch_id')
                ->references('id')
                ->on('ingestion_batches')
                ->cascadeOnDelete();
            $table->unique('ingestion_batch_id');
        });

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->dropForeign(['ingestion_batch_id']);
        });

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('ingestion_batch_id')->nullable(false)->change();
        });

        Schema::table('quotation_review_drafts', function (Blueprint $table) {
            $table->foreign('ingestion_batch_id')
                ->references('id')
                ->on('ingestion_batches')
                ->cascadeOnDelete();
            $table->unique('ingestion_batch_id');
        });
    }
};
