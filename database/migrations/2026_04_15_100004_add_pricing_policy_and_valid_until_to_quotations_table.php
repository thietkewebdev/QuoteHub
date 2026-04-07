<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotations', 'pricing_policy')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('pricing_policy', 32)->default('standard')->after('notes');
            $table->date('valid_until')->nullable()->after('pricing_policy');
            $table->index('pricing_policy');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('quotations', 'pricing_policy')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['pricing_policy']);
            $table->dropIndex(['valid_until']);
            $table->dropColumn(['pricing_policy', 'valid_until']);
        });
    }
};
