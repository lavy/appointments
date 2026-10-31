<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $appointment->business_id === $user->business?->id;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $appointment->business_id === $user->business?->id;
    }
}
