<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_channel', 64);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status', 64)->default('pending');
            $table->unsignedInteger('file_count')->default(0);
            $table->decimal('overall_confidence', 7, 6)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['received_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_batches');
    }
};
