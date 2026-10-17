<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_material_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('total_quantity', 14, 4);
            $table->timestamps();

            $table->unique(['period_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_material_totals');
    }
};
