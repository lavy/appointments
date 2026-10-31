<?php

namespace App\Services;

use App\Models\Business;

class PaymentProofService
{
    public function __construct(
        private AppointmentWorkflow $workflow,
        private WhatsappService $whatsappService,
        private TelegramService $telegramService,
        private MessengerService $messengerService
    ) {
    }

    public function handleWhatsappProof(Business $business, array $message, string $from): ?string
    {
        $mediaId = $message['image']['id'] ?? $message['document']['id'] ?? null;
        $mime = $message['image']['mime_type'] ?? $message['document']['mime_type'] ?? null;

        if (!$mediaId) {
            return null;
        }

        $path = $this->whatsappService->downloadMedia($mediaId, $mime, $business->whatsapp_token);
        return $this->recordProof($business, 'whatsapp', $from, $path);
    }

    public function handleTelegramProof(Business $business, array $message, string $chatId): ?string
    {
        $fileId = null;
        $fileSize = null;

        if (!empty($message['photo']) && is_array($message['photo'])) {
            $photos = array_values($message['photo']);
            $photo = $photos[count($photos) - 1] ?? [];
            $fileId = $photo['file_id'] ?? null;
            $fileSize = isset($photo['file_size']) ? (int) $photo['file_size'] : null;
        } elseif (!empty($message['document']) && is_array($message['document'])) {
            $fileId = $message['document']['file_id'] ?? null;
            $fileSize = isset($message['document']['file_size']) ? (int) $message['document']['file_size'] : null;
        }

        if (!$fileId) {
            return null;
        }

        $path = $this->telegramService->downloadPaymentProof(
            $fileId,
            $business->telegram_bot_token,
            $fileSize
        );

        return $this->recordProof($business, 'telegram', $chatId, $path);
    }

    public function handleMessengerProof(Business $business, array $message, string $senderId): ?string
    {
        foreach ($message['attachments'] ?? [] as $attachment) {
            if (!in_array($attachment['type'] ?? null, ['image', 'file'], true)) {
                continue;
            }

            $url = $attachment['payload']['url'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            $path = $this->messengerService->downloadPaymentProof($url);
            $language = $this->recordProof($business, 'messenger', $senderId, $path);

            if ($language) {
                return $language;
            }
        }

        return null;
    }

    private function recordProof(Business $business, string $channel, string $identifier, ?string $path): ?string
    {
        $appointment = $this->workflow->attachPaymentProof($business, $channel, $identifier, $path);

        if (!$appointment) {
            return null;
        }

        return $appointment->language ?? 'es';
    }
}
