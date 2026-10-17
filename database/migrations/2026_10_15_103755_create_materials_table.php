<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 12)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
