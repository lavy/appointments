<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'contact_channel')) {
                $table->string('contact_channel')->nullable()->after('status');
            }

            if (!Schema::hasColumn('appointments', 'contact_identifier')) {
                $table->string('contact_identifier')->nullable()->after('contact_channel');
            }

            if (!Schema::hasColumn('appointments', 'language')) {
                $table->string('language', 5)->default('es')->after('contact_identifier');
            }

            if (!Schema::hasColumn('appointments', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['contact_channel', 'contact_identifier', 'language', 'reminder_sent_at']);
        });
    }
};
