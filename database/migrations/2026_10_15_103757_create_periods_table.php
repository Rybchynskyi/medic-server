<?php

use App\Enums\Period\PeriodStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 12)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('month');
            $table->smallInteger('year');
            $table->string('status')->default(PeriodStatuses::OPEN);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
