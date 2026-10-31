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
            $table->string('whatsapp_phone_number_id')->nullable()->unique();
            $table->string('whatsapp_verify_token')->nullable();
            $table->string('whatsapp_token')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_secret_token')->nullable();
            $table->string('messenger_page_id')->nullable();
            $table->string('messenger_page_access_token')->nullable();
            $table->string('messenger_verify_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_verify_token',
                'whatsapp_token',
                'telegram_bot_token',
                'telegram_secret_token',
                'messenger_page_id',
                'messenger_page_access_token',
                'messenger_verify_token',
            ]);
        });
    }
};
