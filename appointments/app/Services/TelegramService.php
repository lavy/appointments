<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function sendMessage(string $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram bot token not configured');
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $response = Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if ($response->failed()) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);
        }
    }
}
