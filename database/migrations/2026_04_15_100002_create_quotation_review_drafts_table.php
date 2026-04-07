<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_review_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload_json');
            $table->string('review_status', 32)->default('draft');
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->timestamps();

            $table->unique('ingestion_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_review_drafts');
    }
};
