<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentWorkflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentWorkflow $workflow)
    {
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

        return View::make('dashboard', [
            'appointments' => $appointments,
            'business' => $business,
            'selectedDate' => $selectedDate,
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

        $status = $data['status'] ?? 'confirmed';

        $business->appointments()->create([
            ...$data,
            'status' => $status,
            'contact_channel' => 'panel',
            'contact_identifier' => $data['customer_phone'],
            'language' => 'es',
        ]);

        return Redirect::back()->with('status', 'Turno creado');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $business = Auth::user()?->business;

        if (!$business || $appointment->business_id !== $business->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', Appointment::STATUSES)],
        ]);

        if ($data['status'] === 'confirmed') {
            $this->workflow->approve($appointment, Auth::id());
        } elseif ($data['status'] === 'expired' && $appointment->status === 'pending_review') {
            $this->workflow->reject($appointment, Auth::id());
        } else {
            $appointment->update($data);
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
