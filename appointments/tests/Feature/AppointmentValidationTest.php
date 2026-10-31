<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_appointment_in_past_date(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user)->create([
            'timezone' => 'America/Caracas',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 2, 7, 12, 0, 0, $business->timezone));

        $this->actingAs($user)
            ->post('/appointments', [
                'customer_name' => 'Cliente',
                'customer_phone' => '123',
                'date' => '2026-02-06',
                'time' => '10:00',
                'status' => 'confirmed',
            ])
            ->assertSessionHasErrors(['date']);
    }

    public function test_cannot_create_appointment_in_past_time_today(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->for($user)->create([
            'timezone' => 'America/Caracas',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 2, 7, 12, 0, 0, $business->timezone));

        $this->actingAs($user)
            ->post('/appointments', [
                'customer_name' => 'Cliente',
                'customer_phone' => '123',
                'date' => '2026-02-07',
                'time' => '11:00',
                'status' => 'confirmed',
            ])
            ->assertSessionHasErrors(['time']);
    }
}
