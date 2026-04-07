<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->constrained()->cascadeOnDelete();
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
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique('ingestion_batch_id');
            $table->index(['ai_extraction_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
