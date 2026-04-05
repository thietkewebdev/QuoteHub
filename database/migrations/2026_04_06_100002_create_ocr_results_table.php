<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_file_id')->constrained()->cascadeOnDelete();
            $table->string('engine_name', 64);
            $table->longText('raw_text')->nullable();
            $table->jsonb('structured_blocks')->nullable();
            $table->jsonb('tables_json')->nullable();
            $table->decimal('confidence', 7, 6)->nullable();
            $table->timestamps();

            $table->index(['ingestion_file_id', 'engine_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_results');
    }
};
