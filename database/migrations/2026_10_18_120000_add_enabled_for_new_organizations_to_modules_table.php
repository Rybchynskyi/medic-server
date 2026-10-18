<?php

use App\Enums\ModuleKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('enabled_for_new_organizations')->default(false)->after('description');
        });

        DB::table('modules')
            ->where('key', ModuleKeys::MATERIALS_CALCULATOR)
            ->update(['enabled_for_new_organizations' => true]);
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('enabled_for_new_organizations');
        });
    }
};
