<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('contact_channel')->nullable()->after('status');
            $table->string('contact_identifier')->nullable()->after('contact_channel');
            $table->string('language', 5)->default('es')->after('contact_identifier');
            $table->timestamp('reminder_sent_at')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['contact_channel', 'contact_identifier', 'language', 'reminder_sent_at']);
        });
    }
};
