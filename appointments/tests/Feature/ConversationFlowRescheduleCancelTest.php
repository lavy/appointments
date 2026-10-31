<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\ConversationState;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationFlowRescheduleCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_cancel_appointment_via_chat_flow(): void
    {
        $business = Business::factory()->create([
            'whatsapp_phone_number_id' => '111',
            'whatsapp_token' => 'token-a',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_phone' => '555',
            'contact_channel' => 'whatsapp',
            'contact_identifier' => '555',
            'status' => 'confirmed',
            'date' => '2026-02-10',
            'time' => '10:00',
        ]);

        ConversationState::create([
            'business_id' => $business->id,
            'contact_channel' => 'whatsapp',
            'customer_phone' => '555',
            'current_step' => 'welcome',
            'payload' => [],
        ]);

        $payload = $this->whatsappPayload('111', '555', '2');

        $this->postJson('/api/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $state = ConversationState::where('business_id', $business->id)
            ->where('customer_phone', '555')
            ->first();

        $this->assertSame('awaiting_manage_action', $state->current_step);

        $payloadCancel = $this->whatsappPayload('111', '555', '2');

        $this->postJson('/api/whatsapp/webhook', $payloadCancel)
            ->assertOk();

        $appointment->refresh();
        $this->assertSame('canceled', $appointment->status);
    }

    public function test_can_reschedule_appointment_via_chat_flow(): void
    {
        $business = Business::factory()->create([
            'whatsapp_phone_number_id' => '111',
            'whatsapp_token' => 'token-a',
            'workday_start' => '09:00',
            'workday_end' => '11:00',
            'slot_minutes' => 60,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'customer_phone' => '555',
            'contact_channel' => 'whatsapp',
            'contact_identifier' => '555',
            'status' => 'confirmed',
            'date' => '2026-02-10',
            'time' => '10:00',
        ]);

        ConversationState::create([
            'business_id' => $business->id,
            'contact_channel' => 'whatsapp',
            'customer_phone' => '555',
            'current_step' => 'welcome',
            'payload' => [],
        ]);

        Carbon::setTestNow(Carbon::create(2026, 2, 7, 8, 0, 0, $business->timezone));

        $this->postJson('/api/whatsapp/webhook', $this->whatsappPayload('111', '555', '2'))
            ->assertOk();

        $this->postJson('/api/whatsapp/webhook', $this->whatsappPayload('111', '555', '1'))
            ->assertOk();

        $this->postJson('/api/whatsapp/webhook', $this->whatsappPayload('111', '555', '2026-02-12'))
            ->assertOk();

        $this->postJson('/api/whatsapp/webhook', $this->whatsappPayload('111', '555', '1'))
            ->assertOk();

        $appointment->refresh();
        $this->assertSame('2026-02-12', $appointment->date->format('Y-m-d'));
        $this->assertSame('09:00', $appointment->time->format('H:i'));
        $this->assertSame('confirmed', $appointment->status);
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
}
