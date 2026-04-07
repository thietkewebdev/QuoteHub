<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->text('raw_name')->default('');
            $table->text('raw_name_raw')->nullable();
            $table->string('raw_model', 255)->default('');
            $table->string('brand', 128)->default('');
            $table->string('unit', 64)->default('');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('vat_percent', 10, 4)->nullable();
            $table->decimal('line_total', 18, 4)->nullable();
            $table->text('specs_text')->nullable();
            $table->jsonb('line_snapshot_json')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
