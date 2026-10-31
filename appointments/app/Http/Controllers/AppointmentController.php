<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentStatusNotificationService;
use App\Services\AppointmentWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentWorkflow $workflow,
        private AppointmentStatusNotificationService $statusNotifications
    ) {
    }

    public function index(Request $request)
    {
        $business = Auth::user()?->business;

        $appointments = collect();
        $selectedDate = null;

        if ($business) {
            $selectedDate = $this->resolveDateFilter($request->query('date'), $business->timezone);

            $appointments = $business->appointments()
                ->whereDate('date', $selectedDate->toDateString())
                ->orderBy('time')
                ->get();
        }

        $metrics = [
            'total' => 0,
            'pending_review' => 0,
            'confirmed' => 0,
            'canceled' => 0,
        ];

        if ($business && $selectedDate) {
            $counts = $business->appointments()
                ->selectRaw('status, COUNT(*) as total')
                ->whereDate('date', $selectedDate->toDateString())
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $metrics['total'] = array_sum($counts);
            $metrics['pending_review'] = $counts['pending_review'] ?? 0;
            $metrics['confirmed'] = $counts['confirmed'] ?? 0;
            $metrics['canceled'] = $counts['canceled'] ?? 0;
        }

        return View::make('dashboard', [
            'appointments' => $appointments,
            'business' => $business,
            'selectedDate' => $selectedDate,
            'metrics' => $metrics,
        ]);
    }

    public function store(Request $request)
    {
        $business = Auth::user()?->business;

        if (!$business) {
            abort(403);
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:' . implode(',', Appointment::STATUSES)],
        ]);

        $timezone = $business->timezone ?: config('app.timezone');
        $selectedDate = Carbon::createFromFormat('Y-m-d', $data['date'], $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();

        if ($selectedDate->lt($today)) {
            return Redirect::back()
                ->withErrors(['date' => 'La fecha no puede estar en el pasado.'])
                ->withInput();
        }

        $selectedDateTime = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}", $timezone);
        if ($selectedDateTime->lte(Carbon::now($timezone))) {
            return Redirect::back()
                ->withErrors(['time' => 'La hora no puede estar en el pasado.'])
                ->withInput();
        }

        $status = $data['status'] ?? 'confirmed';

        $appointment = $this->workflow->createAppointmentIfSlotAvailable($business, [
            ...$data,
            'status' => $status,
            'contact_channel' => 'panel',
            'contact_identifier' => $data['customer_phone'],
            'language' => 'es',
        ]);

        if (!$appointment) {
            return Redirect::back()
                ->withErrors(['time' => 'Ese horario ya no está disponible o está fuera del horario de atención.'])
                ->withInput();
        }

        return Redirect::back()->with('status', 'Turno creado');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $business = Auth::user()?->business;

        if (!$business) {
            abort(403);
        }

        $this->authorize('update', $appointment);

        $rules = [
            'status' => ['required', 'in:' . implode(',', Appointment::STATUSES)],
        ];

        if ($request->input('status') === 'expired' && $appointment->status === 'pending_review') {
            $rules['rejection_reason'] = ['required', 'string', 'max:2000'];
        }

        $data = $request->validate($rules);
        $previousStatus = $appointment->status;

        if ($data['status'] === 'confirmed') {
            $updatedAppointment = $this->workflow->approve($appointment, Auth::id());
        } elseif ($data['status'] === 'expired' && $appointment->status === 'pending_review') {
            $updatedAppointment = $this->workflow->reject(
                $appointment,
                Auth::id(),
                $data['rejection_reason']
            );
        } else {
            $updatedAppointment = $this->workflow->changeStatus($appointment, $data['status']);
        }

        if (!$updatedAppointment) {
            return Redirect::back()
                ->withErrors(['status' => 'El estado del turno cambió o el horario ya no está disponible. Actualiza el panel e inténtalo de nuevo.']);
        }

        if ($previousStatus === 'pending_review' && in_array($data['status'], ['confirmed', 'expired'], true)) {
            $notificationSent = $this->statusNotifications->sendPaymentDecision($updatedAppointment);

            if ($notificationSent === false) {
                return Redirect::back()
                    ->with('warning', 'Estado actualizado, pero no se pudo notificar al cliente.');
            }

            if ($notificationSent === true) {
                return Redirect::back()->with('status', 'Estado actualizado y cliente notificado.');
            }
        }

        return Redirect::back()->with('status', 'Estado actualizado');
    }

    private function resolveDateFilter(?string $date, string $timezone): Carbon
    {
        if (!$date) {
            return today($timezone);
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        } catch (\Exception) {
            return today($timezone);
        }
    }
}
