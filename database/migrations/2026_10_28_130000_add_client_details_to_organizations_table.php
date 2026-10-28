<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('our_name', 500)->nullable()->after('name');
            $table->string('director')->nullable()->after('edrpou');
            $table->string('address', 500)->nullable()->after('director');
            $table->string('contact_person')->nullable()->after('address');
            $table->string('contact_phone', 100)->nullable()->after('contact_person');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->text('contracts')->nullable()->after('contact_email');
            $table->text('admin_note')->nullable()->after('contracts');
            $table->timestamp('invited_at')->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'our_name', 'director', 'address', 'contact_person', 'contact_phone',
                'contact_email', 'contracts', 'admin_note', 'invited_at',
            ]);
        });
    }
};
