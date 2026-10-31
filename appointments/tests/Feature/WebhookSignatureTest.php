<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_webhook_rejects_invalid_signature_when_secret_set(): void
    {
        $business = Business::factory()->create([
            'whatsapp_phone_number_id' => '111',
            'whatsapp_token' => 'token-a',
            'whatsapp_app_secret' => 'secret-a',
        ]);

        $payload = $this->whatsappPayload('111', '555', 'hola');
        $json = json_encode($payload);

        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid'],
            $json
        )
            ->assertStatus(403)
            ->assertJson(['status' => 'invalid_signature']);

        $signature = 'sha256=' . hash_hmac('sha256', $json, $business->whatsapp_app_secret);

        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $json
        )
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_messenger_webhook_rejects_invalid_signature_when_secret_set(): void
    {
        $business = Business::factory()->create([
            'messenger_page_id' => 'page-1',
            'messenger_page_access_token' => 'token-a',
            'messenger_app_secret' => 'secret-a',
        ]);

        $payload = $this->messengerPayload('page-1', '999', 'hola');
        $json = json_encode($payload);

        $this->call(
            'POST',
            '/api/messenger/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid'],
            $json
        )
            ->assertStatus(403)
            ->assertJson(['status' => 'invalid_signature']);

        $signature = 'sha256=' . hash_hmac('sha256', $json, $business->messenger_app_secret);

        $this->call(
            'POST',
            '/api/messenger/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $json
        )
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    private function whatsappPayload(string $phoneNumberId, string $from, string $text): array
    {
        return [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => $phoneNumberId],
                                'contacts' => [[
                                    'profile' => ['name' => 'Cliente'],
                                ]],
                                'messages' => [[
                                    'from' => $from,
                                    'text' => ['body' => $text],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function messengerPayload(string $pageId, string $senderId, string $text): array
    {
        return [
            'entry' => [
                [
                    'id' => $pageId,
                    'messaging' => [
                        [
                            'sender' => ['id' => $senderId, 'name' => 'Cliente'],
                            'message' => ['text' => $text],
                        ],
                    ],
                ],
            ],
        ];
    }
}
