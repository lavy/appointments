<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Business;
use Carbon\Carbon;

class AppointmentWorkflow
{
    public const HOLD_MINUTES = 30;

    public function availableSlots(Business $business, string $date): array
    {
        $this->expirePreReservations($business);

        $timezone = $business->timezone ?: 'America/Caracas';
        $start = Carbon::parse($date . ' 09:00', $timezone);
        $end = Carbon::parse($date . ' 17:00', $timezone);
        $now = Carbon::now($timezone);

        $bookedTimes = Appointment::where('business_id', $business->id)
            ->whereDate('date', $date)
            ->whereNotIn('status', ['canceled', 'expired'])
            ->where(function ($query) use ($now) {
                $query->where('status', '!=', 'pre_reserved')
                    ->orWhereNull('pre_reserved_until')
                    ->orWhere('pre_reserved_until', '>', $now);
            })
            ->pluck('time')
            ->map(fn ($time) => Carbon::parse($time)->format('H:i'))
            ->all();

        $available = [];

        for ($slot = $start->copy(); $slot->lte($end); $slot->addMinutes(30)) {
            $formatted = $slot->format('H:i');

            if (!in_array($formatted, $bookedTimes, true)) {
                $available[] = $formatted;
            }
        }

        return $available;
    }

    public function slotIsAvailable(Business $business, string $date, string $time): bool
    {
        $this->expirePreReservations($business);

        $now = Carbon::now($business->timezone ?: config('app.timezone'));

        return !Appointment::where('business_id', $business->id)
            ->whereDate('date', $date)
            ->whereTime('time', $time)
            ->whereNotIn('status', ['canceled', 'expired'])
            ->where(function ($query) use ($now) {
                $query->where('status', '!=', 'pre_reserved')
                    ->orWhereNull('pre_reserved_until')
                    ->orWhere('pre_reserved_until', '>', $now);
            })
            ->exists();
    }

    public function createPreReservation(Business $business, array $data): Appointment
    {
        $timezone = $business->timezone ?: 'America/Caracas';
        $preReservedUntil = Carbon::now($timezone)->addMinutes(self::HOLD_MINUTES);

        return Appointment::create([
            'business_id' => $business->id,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'date' => $data['date'],
            'time' => $data['time'],
            'language' => $data['language'] ?? 'es',
            'status' => 'pre_reserved',
            'contact_channel' => $data['contact_channel'] ?? null,
            'contact_identifier' => $data['contact_identifier'] ?? null,
            'pre_reserved_until' => $preReservedUntil,
        ]);
    }

    public function attachPaymentProof(string $channel, string $identifier, ?string $path): ?Appointment
    {
        $appointment = $this->findLatestAwaitingPayment($channel, $identifier);

        if (!$appointment) {
            return null;
        }

        $appointment->fill([
            'payment_proof_path' => $path,
            'payment_submitted_at' => now(),
            'status' => 'pending_review',
        ])->save();

        return $appointment->refresh();
    }

    public function approve(Appointment $appointment, int $userId): Appointment
    {
        $appointment->update([
            'status' => 'confirmed',
            'payment_reviewed_by' => $userId,
            'payment_reviewed_at' => now(),
        ]);

        return $appointment;
    }

    public function reject(Appointment $appointment, int $userId): Appointment
    {
        $appointment->update([
            'status' => 'expired',
            'payment_reviewed_by' => $userId,
            'payment_reviewed_at' => now(),
        ]);

        return $appointment;
    }

    public function expirePreReservations(?Business $business = null): int
    {
        $now = $business ? Carbon::now($business->timezone ?: config('app.timezone')) : Carbon::now();

        $query = Appointment::where('status', 'pre_reserved')
            ->whereNotNull('pre_reserved_until')
            ->where('pre_reserved_until', '<', $now);

        if ($business) {
            $query->where('business_id', $business->id);
        }

        return $query->update(['status' => 'expired']);
    }

    public function findLatestAwaitingPayment(string $channel, string $identifier): ?Appointment
    {
        return Appointment::where('contact_channel', $channel)
            ->where('contact_identifier', $identifier)
            ->whereIn('status', ['pre_reserved', 'pending_review'])
            ->latest()
            ->first();
    }
}
