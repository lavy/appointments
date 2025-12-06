<?php

namespace Tests\Feature;

use App\Models\Business;
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
