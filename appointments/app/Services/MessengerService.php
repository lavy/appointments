<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    public function sendMessage(string $recipientId, string $text): void
    {
        $token = config('services.messenger.page_access_token');
        $pageId = config('services.messenger.page_id');

        if (!$token || !$pageId) {
            Log::warning('Messenger credentials not configured');
            return;
        }

        $url = "https://graph.facebook.com/v17.0/{$pageId}/messages";

        $response = Http::post($url, [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $text],
            'messaging_type' => 'RESPONSE',
            'access_token' => $token,
        ]);

        if ($response->failed()) {
            Log::error('Failed to send Messenger message', [
                'recipient' => $recipientId,
                'response' => $response->body(),
            ]);
        }
    }
}
