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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->date('date');
            $table->time('time');
            $table->enum('status', ['pre_reserved', 'pending_review', 'confirmed', 'completed', 'canceled', 'expired'])->default('pre_reserved');
            $table->timestamp('pre_reserved_until')->nullable();
            $table->string('contact_channel')->nullable();
            $table->string('contact_identifier')->nullable();
            $table->string('language', 5)->default('es');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->foreignId('payment_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
