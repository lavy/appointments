<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_business_and_manual_appointment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/business', [
                'name' => 'Negocio Demo',
                'phone' => '+58 412-0000000',
                'address' => 'Caracas',
                'timezone' => 'America/Caracas',
            ])->assertRedirect();

        $business = Business::first();

        $this->assertNotNull($business);
        $this->assertEquals('America/Caracas', $business->timezone);

        $this->actingAs($user)
            ->post('/appointments', [
                'customer_name' => 'Cliente Demo',
                'customer_phone' => '+58 414-0000000',
                'date' => now()->toDateString(),
                'time' => '10:30',
            ])->assertRedirect();

        $appointment = Appointment::first();

        $this->assertNotNull($appointment);
        $this->assertEquals($business->id, $appointment->business_id);
        $this->assertEquals('pending', $appointment->status);
    }

    public function test_user_cannot_update_other_business_appointment(): void
    {
        $businessOwner = User::factory()->create();
        $otherUser = User::factory()->create();

        $business = Business::factory()->for($businessOwner)->create();
        $appointment = Appointment::factory()->for($business)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser)
            ->post('/appointments/' . $appointment->id . '/status', [
                'status' => 'confirmed',
            ])
            ->assertStatus(403);

        $this->assertEquals('pending', $appointment->fresh()->status);
    }
}
