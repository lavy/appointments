<?php

namespace App\Services;

use App\Models\Business;

class BusinessResolver
{
    public function resolveByWhatsapp(string $phoneNumberId): ?Business
    {
        return Business::where('whatsapp_phone_number_id', $phoneNumberId)->first();
    }

    public function resolveByTelegramBotToken(string $botToken): ?Business
    {
        return Business::where('telegram_bot_token', $botToken)->first();
    }

    public function resolveByMessengerPageId(string $pageId): ?Business
    {
        return Business::where('messenger_page_id', $pageId)->first();
    }

    public function resolveByUserId(int $userId): ?Business
    {
        return Business::where('user_id', $userId)->first();
    }
}
