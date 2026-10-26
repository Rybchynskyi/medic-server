<?php

use App\Enums\UserStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable: organizations created before this migration have no code yet.
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('edrpou', 8)->nullable()->unique()->after('name');
        });

        // Existing users keep working; only new self-registrations start as pending.
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default(UserStatuses::ACTIVE)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['edrpou']);
            $table->dropColumn('edrpou');
        });
    }
};
