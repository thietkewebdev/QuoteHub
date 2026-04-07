<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_extractions', function (Blueprint $table) {
            $table->foreignId('supplier_extraction_profile_id')
                ->nullable()
                ->after('ingestion_batch_id')
                ->constrained('supplier_extraction_profiles')
                ->nullOnDelete();
            $table->string('supplier_profile_mode', 32)->default('none')->after('supplier_extraction_profile_id');
            $table->jsonb('supplier_profile_inference')->nullable()->after('supplier_profile_mode');
        });
    }

    public function down(): void
    {
        Schema::table('ai_extractions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_extraction_profile_id');
            $table->dropColumn(['supplier_profile_mode', 'supplier_profile_inference']);
        });
    }
};
