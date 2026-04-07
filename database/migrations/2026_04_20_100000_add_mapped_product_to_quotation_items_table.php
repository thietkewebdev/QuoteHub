<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('mapped_product_id')->nullable()->after('line_snapshot_json')->constrained('products')->nullOnDelete();
            $table->timestamp('mapped_at')->nullable()->after('mapped_product_id');
            $table->foreignId('mapped_by')->nullable()->after('mapped_at')->constrained('users')->nullOnDelete();
            $table->index('mapped_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['mapped_product_id']);
            $table->dropForeign(['mapped_by']);
            $table->dropIndex(['mapped_product_id']);
            $table->dropColumn(['mapped_product_id', 'mapped_at', 'mapped_by']);
        });
    }
};
