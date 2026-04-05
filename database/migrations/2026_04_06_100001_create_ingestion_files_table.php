<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_batch_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->string('extension', 32)->nullable();
            $table->string('storage_path', 2048)->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('page_order')->default(0);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->jsonb('preprocessing_meta')->nullable();
            $table->timestamps();

            $table->index(['ingestion_batch_id', 'page_order']);
            $table->index('checksum_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_files');
    }
};
