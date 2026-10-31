<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const MAX_ATTEMPTS = 3;

    public function sendMessage(string $chatId, string $text, ?string $token = null): bool
    {
        $token ??= config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram bot token not configured');
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            Log::warning('Telegram send attempt failed', [
                'chat_id' => $chatId,
                'attempt' => $attempt,
                'response' => $response->body(),
            ]);

            usleep(200000);
        }

        Log::error('Failed to send Telegram message after retries', [
            'chat_id' => $chatId,
        ]);

        return false;
    }

    public function downloadPaymentProof(string $fileId, ?string $token = null, ?int $fileSize = null): ?string
    {
        $token ??= config('services.telegram.bot_token');

        if (!$token || ($fileSize !== null && $fileSize > PaymentProofFileStore::MAX_BYTES)) {
            Log::warning('Telegram payment proof is missing credentials or exceeds the size limit');
            return null;
        }

        try {
            $metadata = Http::timeout(20)->get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);

            $filePath = $metadata->json('result.file_path');
            if (!$metadata->successful() || $metadata->json('ok') !== true || !is_string($filePath) || $filePath === '') {
                Log::warning('Failed to retrieve Telegram payment proof metadata', [
                    'file_id' => $fileId,
                    'response' => $metadata->body(),
                ]);
                return null;
            }

            $file = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");
            if (!$file->successful()) {
                Log::warning('Failed to download Telegram payment proof', [
                    'file_id' => $fileId,
                    'status' => $file->status(),
                ]);
                return null;
            }

            $path = PaymentProofFileStore::store($file->body());
            if (!$path) {
                Log::warning('Telegram payment proof was not an accepted file', ['file_id' => $fileId]);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('Telegram payment proof download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
