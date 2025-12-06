<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $data['user_id'] = Auth::id();

        $business = Business::create($data);

        return redirect()->back()->with('status', 'Business created');
    }

    public function update(Request $request, Business $business)
    {
        if ($business->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $business->update($data);

        return redirect()->back()->with('status', 'Business updated');
    }
}
