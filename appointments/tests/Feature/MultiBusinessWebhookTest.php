<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ConversationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBusinessWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_webhook_resolves_business_by_phone_number_id(): void
    {
        $businessA = Business::factory()->create([
            'whatsapp_phone_number_id' => '111',
            'whatsapp_token' => 'token-a',
        ]);
        Business::factory()->create([
            'whatsapp_phone_number_id' => '222',
            'whatsapp_token' => 'token-b',
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => '111'],
                                'contacts' => [[
                                    'profile' => ['name' => 'Ana'],
                                ]],
                                'messages' => [[
                                    'from' => '555',
                                    'text' => ['body' => 'hola'],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('conversation_states', [
            'business_id' => $businessA->id,
            'contact_channel' => 'whatsapp',
            'customer_phone' => '555',
        ]);
    }

    public function test_telegram_webhook_requires_secret_when_multiple_businesses(): void
    {
        $businessA = Business::factory()->create([
            'telegram_secret_token' => 'secret-a',
            'telegram_bot_token' => 'token-a',
        ]);
        Business::factory()->create([
            'telegram_secret_token' => 'secret-b',
            'telegram_bot_token' => 'token-b',
        ]);

        $payload = [
            'message' => [
                'chat' => ['id' => '777'],
                'text' => 'hola',
                'from' => ['first_name' => 'Ana'],
            ],
        ];

        $this->postJson('/api/telegram/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'no_business']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-a')
            ->postJson('/api/telegram/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('conversation_states', [
            'business_id' => $businessA->id,
            'contact_channel' => 'telegram',
            'customer_phone' => '777',
        ]);
    }

    public function test_messenger_webhook_resolves_business_by_page_id(): void
    {
        $businessA = Business::factory()->create([
            'messenger_page_id' => 'page-1',
            'messenger_page_access_token' => 'token-a',
        ]);
        Business::factory()->create([
            'messenger_page_id' => 'page-2',
            'messenger_page_access_token' => 'token-b',
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => 'page-1',
                    'messaging' => [
                        [
                            'sender' => ['id' => '999', 'name' => 'Ana'],
                            'message' => ['text' => 'hola'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/messenger/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('conversation_states', [
            'business_id' => $businessA->id,
            'contact_channel' => 'messenger',
            'customer_phone' => '999',
        ]);
    }
}
