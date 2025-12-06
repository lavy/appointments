<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Turnos Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        Business::create([
            'user_id' => $user->id,
            'name' => 'Venezuela Tecnológica',
            'phone' => '+584120000000',
            'address' => 'Caracas',
            'payment_instructions' => 'Paga por Pago Móvil 0102-0123456789 (V-12345678) o Zelle venezuela@tecnologica.com y envía el comprobante.',
        ]);
    }
}
