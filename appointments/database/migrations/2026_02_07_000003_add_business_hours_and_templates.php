<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->time('workday_start')->nullable();
            $table->time('workday_end')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->nullable();
            $table->json('message_templates')->nullable();
            $table->string('whatsapp_app_secret')->nullable();
            $table->string('messenger_app_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'workday_start',
                'workday_end',
                'slot_minutes',
                'message_templates',
                'whatsapp_app_secret',
                'messenger_app_secret',
            ]);
        });
    }
};
