<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;

class AppointmentController extends Controller
{
    public function index()
    {
        $business = Auth::user()?->business;

        $appointments = collect();

        if ($business) {
            $appointments = $business->appointments()
                ->whereDate('date', today($business->timezone))
                ->orderBy('time')
                ->get();
        }

        return View::make('dashboard', [
            'appointments' => $appointments,
            'business' => $business,
        ]);
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

        $appointment->update($data);

        return Redirect::back()->with('status', 'Estado actualizado');
    }
}
