<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 512);
            $table->string('alias_type', 32)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'alias']);
            $table->index('alias_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};
