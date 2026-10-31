<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    private const MAX_ATTEMPTS = 3;

    public function sendMessage(string $to, string $text, ?string $token = null, ?string $phoneNumberId = null): bool
    {
        $token ??= config('services.whatsapp.token');
        $phoneNumberId ??= config('services.whatsapp.phone_number_id');

        if (!$token || !$phoneNumberId) {
            Log::warning('WhatsApp credentials are not configured');
            return false;
        }

        $url = sprintf('https://graph.facebook.com/v18.0/%s/messages', $phoneNumberId);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::withToken($token)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $text],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsApp send attempt failed', [
                'status' => $response->status(),
                'attempt' => $attempt,
                'body' => $response->body(),
            ]);

            usleep(200000);
        }

        Log::error('WhatsApp API error after retries', [
            'to' => $to,
        ]);

        return false;
    }

    public function downloadMedia(string $mediaId, ?string $mimeType, ?string $token = null): ?string
    {
        $token ??= config('services.whatsapp.token');

        if (!$token) {
            Log::warning('Cannot download WhatsApp media without token');
            return null;
        }

        $metaResponse = Http::withToken($token)
            ->get(sprintf('https://graph.facebook.com/v18.0/%s', $mediaId));

        if ($metaResponse->failed() || !isset($metaResponse['url'])) {
            Log::error('Failed to fetch media metadata', ['media_id' => $mediaId, 'body' => $metaResponse->body()]);
            return null;
        }

        $fileResponse = Http::withToken($token)->get($metaResponse['url']);

        if ($fileResponse->failed()) {
            Log::error('Failed to download media file', ['media_id' => $mediaId, 'body' => $fileResponse->body()]);
            return null;
        }

        $path = PaymentProofFileStore::store($fileResponse->body());

        if (!$path) {
            Log::warning('WhatsApp media was not an accepted payment proof', [
                'media_id' => $mediaId,
            ]);
        }

        return $path;
    }
}
