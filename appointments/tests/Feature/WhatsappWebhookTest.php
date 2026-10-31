<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Services\MessengerService;
use App\Services\TelegramService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_message_in_english_and_portuguese(): void
    {
        $business = Business::factory()->create(['name' => 'Venezuela Tecnológica']);
        $fakeService = new class extends WhatsappService {
            public array $messages = [];

            public function sendMessage(string $to, string $text): void
            {
                $this->messages[] = ['to' => $to, 'text' => $text];
            }
        };

        $this->app->instance(WhatsappService::class, $fakeService);

        $englishPayload = $this->payload('+1111', 'Hello there');
        $portuguesePayload = $this->payload('+2222', 'Olá, quero marcar');

        $this->postJson('/api/whatsapp/webhook', $englishPayload)->assertOk();
        $this->postJson('/api/whatsapp/webhook', $portuguesePayload)->assertOk();

        $this->assertCount(2, $fakeService->messages);
        $this->assertStringContainsString('Hi! I\'m the booking assistant', $fakeService->messages[0]['text']);
        $this->assertStringContainsString('Olá, sou o assistente de agendamentos', $fakeService->messages[1]['text']);
    }

    public function test_telegram_webhook_responds_with_multilingual_greeting(): void
    {
        $business = Business::factory()->create(['name' => 'Venezuela Tecnológica']);

        $fakeService = new class extends TelegramService {
            public array $messages = [];

            public function sendMessage(string $chatId, string $text): void
            {
                $this->messages[] = ['to' => $chatId, 'text' => $text];
            }
        };

        $this->app->instance(TelegramService::class, $fakeService);

        $payload = [
            'message' => [
                'chat' => ['id' => 999],
                'text' => 'Hi there',
                'from' => ['first_name' => 'Tester'],
            ],
        ];

        $this->postJson('/api/telegram/webhook', $payload)->assertOk();

        $this->assertCount(1, $fakeService->messages);
        $this->assertStringContainsString("I'm the booking assistant", $fakeService->messages[0]['text']);
    }

    public function test_messenger_webhook_responds_with_multilingual_greeting(): void
    {
        $business = Business::factory()->create(['name' => 'Venezuela Tecnológica']);

        $fakeService = new class extends MessengerService {
            public array $messages = [];

            public function sendMessage(string $recipientId, string $text): void
            {
                $this->messages[] = ['to' => $recipientId, 'text' => $text];
            }
        };

        $this->app->instance(MessengerService::class, $fakeService);

        $payload = [
            'entry' => [[
                'messaging' => [[
                    'sender' => ['id' => 'abc123', 'name' => 'Tester'],
                    'message' => ['text' => 'Olá bot'],
                ]],
            ]],
        ];

        $this->postJson('/api/messenger/webhook', $payload)->assertOk();

        $this->assertCount(1, $fakeService->messages);
        $this->assertStringContainsString('Olá, sou o assistente de agendamentos', $fakeService->messages[0]['text']);
    }

    public function test_whatsapp_booking_lists_slots_and_accepts_number_selection(): void
    {
        $business = Business::factory()->create(['name' => 'Turnos360 Demo']);

        $fakeService = new class extends WhatsappService {
            public array $messages = [];

            public function sendMessage(string $to, string $text): void
            {
                $this->messages[] = ['to' => $to, 'text' => $text];
            }
        };

        $this->app->instance(WhatsappService::class, $fakeService);

        $from = '+1111';
        $date = now()->addDay()->toDateString();

        $this->postJson('/api/whatsapp/webhook', $this->payload($from, '1'))->assertOk();
        $this->postJson('/api/whatsapp/webhook', $this->payload($from, $date))->assertOk();
        $this->postJson('/api/whatsapp/webhook', $this->payload($from, '1'))->assertOk();

        $this->assertCount(3, $fakeService->messages);
        $this->assertStringContainsString('¿Para qué día quieres el turno?', $fakeService->messages[0]['text']);
        $this->assertStringContainsString('1) 09:00', $fakeService->messages[1]['text']);
        $this->assertStringContainsString('09:00', $fakeService->messages[2]['text']);

        $this->assertDatabaseHas('appointments', [
            'business_id' => $business->id,
            'date' => $date,
            'time' => '09:00:00',
            'customer_phone' => $from,
        ]);
    }

    private function payload(string $from, string $message): array
    {
        return [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'text' => ['body' => $message],
                                    ],
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Tester'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
