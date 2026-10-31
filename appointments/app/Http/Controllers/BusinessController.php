<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function store(Request $request)
    {
        if (Auth::user()?->business) {
            return redirect()->back()->withErrors(['name' => 'Ya existe un negocio asociado a este usuario.']);
        }

        $this->normalizeClosedDates($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'payment_instructions' => ['nullable', 'string'],
            'workday_start' => ['nullable', 'date_format:H:i'],
            'workday_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'closed_dates' => ['nullable', 'array'],
            'closed_dates.*' => ['date_format:Y-m-d', 'distinct'],
            'message_templates' => ['nullable', 'json'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:100', 'unique:businesses,whatsapp_phone_number_id'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:255'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_secret_token' => ['nullable', 'string', 'max:255'],
            'messenger_page_id' => ['nullable', 'string', 'max:100', 'unique:businesses,messenger_page_id'],
            'messenger_page_access_token' => ['nullable', 'string', 'max:255'],
            'messenger_verify_token' => ['nullable', 'string', 'max:255'],
            'messenger_app_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $data['user_id'] = Auth::id();
        $data['timezone'] ??= 'America/Caracas';

        $business = Business::create($data);

        return redirect()->back()->with('status', 'Business created');
    }

    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $this->normalizeClosedDates($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'payment_instructions' => ['nullable', 'string'],
            'workday_start' => ['nullable', 'date_format:H:i'],
            'workday_end' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'closed_dates' => ['nullable', 'array'],
            'closed_dates.*' => ['date_format:Y-m-d', 'distinct'],
            'message_templates' => ['nullable', 'json'],
            'whatsapp_phone_number_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('businesses', 'whatsapp_phone_number_id')->ignore($business->id),
            ],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:255'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_secret_token' => ['nullable', 'string', 'max:255'],
            'messenger_page_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('businesses', 'messenger_page_id')->ignore($business->id),
            ],
            'messenger_page_access_token' => ['nullable', 'string', 'max:255'],
            'messenger_verify_token' => ['nullable', 'string', 'max:255'],
            'messenger_app_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $data['timezone'] ??= 'America/Caracas';

        $business->update($data);

        return redirect()->back()->with('status', 'Business updated');
    }

    private function normalizeClosedDates(Request $request): void
    {
        $closedDates = $request->input('closed_dates');

        if (!is_string($closedDates)) {
            return;
        }

        $dates = preg_split('/\\R/', $closedDates) ?: [];
        $dates = array_values(array_unique(array_filter(array_map('trim', $dates), fn ($date) => $date !== '')));

        $request->merge(['closed_dates' => $dates]);
    }
}
