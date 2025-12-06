<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function sendMessage(string $to, string $text): void
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$token || !$phoneNumberId) {
            Log::warning('WhatsApp credentials are not configured');
            return;
        }

        $url = sprintf('https://graph.facebook.com/v18.0/%s/messages', $phoneNumberId);

        $response = Http::withToken($token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);

        if ($response->failed()) {
            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
