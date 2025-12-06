<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Services\MessengerService;
use App\Services\TelegramService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_via_original_channel(): void
    {
        Carbon::setTestNow('2024-01-01 10:00:00');

        $business = Business::factory()->create(['timezone' => 'America/Caracas']);

        $appointment = Appointment::factory()->for($business)->create([
            'date' => Carbon::now('America/Caracas')->addDays(2)->toDateString(),
            'time' => '14:00',
            'status' => 'confirmed',
            'contact_channel' => 'telegram',
            'contact_identifier' => '123456',
            'language' => 'en',
        ]);

        $fakeTelegram = new class extends TelegramService {
            public array $messages = [];
            public function sendMessage(string $chatId, string $text): void
            {
                $this->messages[] = ['to' => $chatId, 'text' => $text];
            }
        };

        $this->app->instance(TelegramService::class, $fakeTelegram);
        $this->app->instance(WhatsappService::class, new class extends WhatsappService {});
        $this->app->instance(MessengerService::class, new class extends MessengerService {});

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        $this->assertCount(1, $fakeTelegram->messages);
        $this->assertStringContainsString('Reminder: you have an appointment', $fakeTelegram->messages[0]['text']);
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }
}
