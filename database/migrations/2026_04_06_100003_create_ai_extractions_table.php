<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->constrained()->cascadeOnDelete();
            $table->string('model_name', 128);
            $table->string('prompt_version', 64);
            $table->jsonb('extraction_json');
            $table->decimal('confidence_overall', 7, 6)->nullable();
            $table->jsonb('warnings')->nullable();
            $table->timestamps();

            $table->index(['ingestion_batch_id', 'model_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_extractions');
    }
};
