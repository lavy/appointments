<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentWorkflow
{
    public const HOLD_MINUTES = 30;

    public function availableSlots(Business $business, string $date): array
    {
        $this->expirePreReservations($business);

        $timezone = $business->timezone ?: 'America/Caracas';
        $startTime = $business->workday_start ?? '09:00';
        $endTime = $business->workday_end ?? '17:00';
        $slotMinutes = $business->slot_minutes ?? 30;
        $workingDays = array_map('intval', $business->working_days ?? range(1, 7));
        $closedDates = $business->closed_dates ?? [];
        $selectedDate = Carbon::createFromFormat('Y-m-d', $date, $timezone);

        if (!in_array($selectedDate->dayOfWeekIso, $workingDays, true)
            || in_array($selectedDate->toDateString(), $closedDates, true)) {
            return [];
        }

        $start = Carbon::parse($date . ' ' . $startTime, $timezone);
        $end = Carbon::parse($date . ' ' . $endTime, $timezone);
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

        for ($slot = $start->copy(); $slot->lte($end); $slot->addMinutes($slotMinutes)) {
            if ($slot->isSameDay($now) && $slot->lte($now)) {
                continue;
            }

            $formatted = $slot->format('H:i');

            if (!in_array($formatted, $bookedTimes, true)) {
                $available[] = $formatted;
            }
        }

        return $available;
    }

    public function slotIsAvailable(Business $business, string $date, string $time): bool
    {
        $timezone = $business->timezone ?: config('app.timezone');
        $now = Carbon::now($timezone);
        $selected = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone);

        if ($selected->lte($now)) {
            return false;
        }

        return in_array($time, $this->availableSlots($business, $date), true);
    }

    public function createPreReservation(Business $business, array $data): ?Appointment
    {
        $timezone = $business->timezone ?: 'America/Caracas';
        $data['status'] = 'pre_reserved';
        $data['pre_reserved_until'] = Carbon::now($timezone)->addMinutes(self::HOLD_MINUTES);

        return $this->createAppointmentIfSlotAvailable($business, $data);
    }

    public function createAppointmentIfSlotAvailable(Business $business, array $data): ?Appointment
    {
        return DB::transaction(function () use ($business, $data): ?Appointment {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->expirePreReservations($lockedBusiness);

            if (!$this->slotIsAvailable($lockedBusiness, $data['date'], $data['time'])) {
                return null;
            }

            if (($data['status'] ?? null) === 'pre_reserved' && empty($data['pre_reserved_until'])) {
                $timezone = $lockedBusiness->timezone ?: config('app.timezone');
                $data['pre_reserved_until'] = Carbon::now($timezone)->addMinutes(self::HOLD_MINUTES);
            }

            return $lockedBusiness->appointments()->create([
                ...$data,
                'language' => $data['language'] ?? 'es',
            ]);
        });
    }

    public function attachPaymentProof(Business $business, string $channel, string $identifier, ?string $path): ?Appointment
    {
        if (!$path) {
            return null;
        }

        return DB::transaction(function () use ($business, $channel, $identifier, $path): ?Appointment {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->expirePreReservations($lockedBusiness);
            $appointment = $this->findLatestAwaitingPayment($lockedBusiness, $channel, $identifier);

            if (!$appointment) {
                return null;
            }

            $appointment->fill([
                'payment_proof_path' => $path,
                'payment_submitted_at' => now(),
                'status' => 'pending_review',
            ])->save();

            return $appointment->refresh();
        });
    }

    public function findLatestActiveByContact(Business $business, string $channel, string $identifier): ?Appointment
    {
        return Appointment::where('business_id', $business->id)
            ->where('contact_channel', $channel)
            ->where('contact_identifier', $identifier)
            ->whereIn('status', ['confirmed', 'pre_reserved', 'pending_review'])
            ->latest()
            ->first();
    }

    public function reschedule(Appointment $appointment, string $date, string $time): ?Appointment
    {
        $business = $appointment->business;

        if (!$business) {
            return null;
        }

        return DB::transaction(function () use ($appointment, $business, $date, $time): ?Appointment {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAppointment = $lockedBusiness->appointments()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedAppointment) {
                return null;
            }

            $sameSlot = $lockedAppointment->date->format('Y-m-d') === $date
                && $lockedAppointment->time->format('H:i') === $time;

            $this->expirePreReservations($lockedBusiness);

            if (!$sameSlot && !$this->slotIsAvailable($lockedBusiness, $date, $time)) {
                return null;
            }

            $updates = [
                'date' => $date,
                'time' => $time,
            ];

            if ($lockedAppointment->status !== 'confirmed') {
                $timezone = $lockedBusiness->timezone ?: config('app.timezone');
                $updates['status'] = 'pre_reserved';
                $updates['pre_reserved_until'] = Carbon::now($timezone)->addMinutes(self::HOLD_MINUTES);
            }

            $lockedAppointment->update($updates);

            return $lockedAppointment->refresh();
        });
    }

    public function changeStatus(
        Appointment $appointment,
        string $status,
        ?int $reviewedBy = null,
        ?string $rejectionReason = null,
        ?string $expectedStatus = null
    ): ?Appointment
    {
        $business = $appointment->business;

        if (!$business) {
            return null;
        }

        return DB::transaction(function () use ($appointment, $business, $status, $reviewedBy, $rejectionReason, $expectedStatus): ?Appointment {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAppointment = $lockedBusiness->appointments()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedAppointment) {
                return null;
            }

            $this->expirePreReservations($lockedBusiness);
            $lockedAppointment->refresh();

            if ($expectedStatus !== null && $lockedAppointment->status !== $expectedStatus) {
                return null;
            }

            $currentOccupiesSlot = !in_array($lockedAppointment->status, ['canceled', 'expired'], true);
            $targetOccupiesSlot = !in_array($status, ['canceled', 'expired'], true);

            if (!$currentOccupiesSlot && $targetOccupiesSlot
                && !$this->slotIsAvailable($lockedBusiness, $lockedAppointment->date->format('Y-m-d'), $lockedAppointment->time->format('H:i'))) {
                return null;
            }

            $updates = ['status' => $status];

            if ($status === 'pre_reserved') {
                $timezone = $lockedBusiness->timezone ?: config('app.timezone');
                $updates['pre_reserved_until'] = Carbon::now($timezone)->addMinutes(self::HOLD_MINUTES);
            }

            if ($status === 'expired') {
                $updates['payment_rejection_reason'] = $rejectionReason;
            } elseif ($status === 'confirmed') {
                $updates['payment_rejection_reason'] = null;
            }

            if ($reviewedBy !== null) {
                $updates['payment_reviewed_by'] = $reviewedBy;
                $updates['payment_reviewed_at'] = now();
            }

            $lockedAppointment->update($updates);

            return $lockedAppointment->refresh();
        });
    }

    public function approve(Appointment $appointment, int $userId): ?Appointment
    {
        return $this->changeStatus($appointment, 'confirmed', $userId, null, 'pending_review');
    }

    public function reject(Appointment $appointment, int $userId, string $reason): ?Appointment
    {
        return $this->changeStatus($appointment, 'expired', $userId, $reason, 'pending_review');
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

    public function findLatestAwaitingPayment(Business $business, string $channel, string $identifier): ?Appointment
    {
        return Appointment::where('business_id', $business->id)
            ->where('contact_channel', $channel)
            ->where('contact_identifier', $identifier)
            ->whereIn('status', ['pre_reserved', 'pending_review'])
            ->latest()
            ->lockForUpdate()
            ->first();
    }
}
