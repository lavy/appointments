<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    private const MAX_ATTEMPTS = 3;

    public function sendMessage(string $recipientId, string $text, ?string $token = null, ?string $pageId = null): bool
    {
        $token ??= config('services.messenger.page_access_token');
        $pageId ??= config('services.messenger.page_id');

        if (!$token || !$pageId) {
            Log::warning('Messenger credentials not configured');
            return false;
        }

        $url = "https://graph.facebook.com/v17.0/{$pageId}/messages";

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = Http::post($url, [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE',
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Messenger send attempt failed', [
                'recipient' => $recipientId,
                'attempt' => $attempt,
                'response' => $response->body(),
            ]);

            usleep(200000);
        }

        Log::error('Failed to send Messenger message after retries', [
            'recipient' => $recipientId,
        ]);

        return false;
    }

    public function downloadPaymentProof(string $url): ?string
    {
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            if (!$this->isTrustedAttachmentUrl($url)) {
                Log::warning('Rejected Messenger payment proof URL with an untrusted host');
                return null;
            }

            try {
                $response = Http::timeout(30)
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);
            } catch (\Throwable $e) {
                Log::error('Messenger payment proof download failed', ['error' => $e->getMessage()]);
                return null;
            }

            if ($response->successful()) {
                $path = PaymentProofFileStore::store($response->body());
                if (!$path) {
                    Log::warning('Messenger payment proof was not an accepted file');
                }

                return $path;
            }

            if (!in_array($response->status(), [301, 302, 303, 307, 308], true) || $redirects === 3) {
                Log::warning('Failed to retrieve Messenger payment proof', ['status' => $response->status()]);
                return null;
            }

            $location = $response->header('Location');
            $url = $location ? $this->resolveAttachmentRedirect($url, $location) : null;
            if (!$url) {
                return null;
            }
        }

        return null;
    }

    private function isTrustedAttachmentUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }

        return $host === 'lookaside.fbsbx.com'
            || $host === 'fbcdn.net'
            || str_ends_with($host, '.fbcdn.net')
            || str_ends_with($host, '.fbsbx.com');
    }

    private function resolveAttachmentRedirect(string $baseUrl, string $location): ?string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (!isset($base['host'])) {
            return null;
        }

        $origin = ($base['scheme'] ?? 'https') . '://' . $base['host'];
        if (isset($base['port'])) {
            $origin .= ':' . $base['port'];
        }

        if (str_starts_with($location, '//')) {
            return ($base['scheme'] ?? 'https') . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $directory = isset($base['path']) ? dirname($base['path']) : '';
        return $origin . ($directory === '/' ? '' : $directory) . '/' . $location;
    }
}
