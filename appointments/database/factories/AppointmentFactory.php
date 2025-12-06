<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $time = fake()->time('H:i');

        return [
            'business_id' => Business::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'date' => now()->toDateString(),
            'time' => $time,
            'status' => 'pending',
            'contact_channel' => 'whatsapp',
            'contact_identifier' => fake()->e164PhoneNumber(),
            'language' => 'es',
        ];
    }
}
