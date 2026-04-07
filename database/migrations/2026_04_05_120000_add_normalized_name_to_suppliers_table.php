<?php

use App\Support\Supplier\SupplierNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('normalized_name', 512)->nullable()->after('name');
        });

        DB::table('suppliers')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $normalized = SupplierNameNormalizer::normalize((string) $row->name);
                DB::table('suppliers')->where('id', $row->id)->update([
                    'normalized_name' => $normalized !== '' ? $normalized : null,
                ]);
            }
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->unique('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['normalized_name']);
            $table->dropColumn('normalized_name');
        });
    }
};
