<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->boolean('is_latest')->default(false);
            $table->string('model_name', 128);
            $table->string('prompt_version', 64);
            $table->jsonb('result_json');
            $table->decimal('confidence_overall', 7, 6)->nullable();
            $table->timestamps();

            $table->index(['ingestion_batch_id', 'is_latest']);
            $table->unique(['ingestion_batch_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_attempts');
    }
};
