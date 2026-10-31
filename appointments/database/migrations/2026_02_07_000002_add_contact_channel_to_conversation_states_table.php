<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('conversation_states', 'contact_channel')) {
            Schema::table('conversation_states', function (Blueprint $table) {
                $table->string('contact_channel')->default('whatsapp')->index();
            });
        }

        DB::table('conversation_states')
            ->whereNull('contact_channel')
            ->update(['contact_channel' => 'whatsapp']);

        // Ensure a dedicated index for the foreign key before dropping the unique composite index.
        if (!$this->indexExists('conversation_states_business_id_index')) {
            DB::statement('CREATE INDEX conversation_states_business_id_index ON conversation_states (business_id)');
        }

        if ($this->indexExists('conversation_states_business_id_customer_phone_unique')) {
            DB::statement('DROP INDEX conversation_states_business_id_customer_phone_unique ON conversation_states');
        }

        if (!$this->indexExists('conv_states_biz_channel_phone_unique')) {
            DB::statement('CREATE UNIQUE INDEX conv_states_biz_channel_phone_unique ON conversation_states (business_id, contact_channel, customer_phone)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('conv_states_biz_channel_phone_unique')) {
            DB::statement('DROP INDEX conv_states_biz_channel_phone_unique ON conversation_states');
        }

        if (!$this->indexExists('conversation_states_business_id_customer_phone_unique')) {
            DB::statement('CREATE UNIQUE INDEX conversation_states_business_id_customer_phone_unique ON conversation_states (business_id, customer_phone)');
        }

        if (Schema::hasColumn('conversation_states', 'contact_channel')) {
            Schema::table('conversation_states', function (Blueprint $table) {
                $table->dropColumn('contact_channel');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $result = DB::select(
            'SHOW INDEX FROM conversation_states WHERE Key_name = ?',
            [$indexName]
        );

        return count($result) > 0;
    }
};
