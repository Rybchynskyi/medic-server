<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract the service was taken from (e.g. "1750/м"), shown to admins only.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('contract')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('contract');
        });
    }
};
