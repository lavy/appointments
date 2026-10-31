<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\AppointmentWorkflow;
use App\Services\ConversationFlowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTemplatesAndHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_templates_override_default_text(): void
    {
        $business = Business::factory()->create([
            'message_templates' => [
                'es' => [
                    'welcome' => 'Bienvenido personalizado de :business',
                ],
            ],
        ]);

        $state = ConversationState::create([
            'business_id' => $business->id,
            'contact_channel' => 'whatsapp',
            'customer_phone' => '555',
            'current_step' => 'welcome',
            'payload' => [],
        ]);

        $service = app(ConversationFlowService::class);

        $response = $service->respond($business, $state, 'hola', 'Cliente', 'whatsapp');

        $this->assertSame('Bienvenido personalizado de ' . $business->name, $response);
    }

    public function test_available_slots_respect_business_hours_and_slot_minutes(): void
    {
        $business = Business::factory()->create([
            'workday_start' => '10:00',
            'workday_end' => '11:00',
            'slot_minutes' => 60,
            'timezone' => 'America/Caracas',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 2, 7, 9, 0, 0, $business->timezone));

        $workflow = app(AppointmentWorkflow::class);

        $slots = $workflow->availableSlots($business, '2026-02-10');

        $this->assertSame(['10:00', '11:00'], $slots);
    }
}
