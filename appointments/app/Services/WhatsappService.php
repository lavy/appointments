<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    public function downloadMedia(string $mediaId, ?string $mimeType): ?string
    {
        $token = config('services.whatsapp.token');

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

        $extension = $this->extensionFromMime($mimeType) ?? 'bin';
        $path = 'payment-proofs/' . $mediaId . '.' . $extension;

        Storage::disk('public')->put($path, $fileResponse->body());

        return $path;
    }

    private function extensionFromMime(?string $mime): ?string
    {
        if (!$mime) {
            return null;
        }

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => null,
        };
    }
}
