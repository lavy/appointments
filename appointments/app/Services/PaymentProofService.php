<?php

namespace App\Services;


class PaymentProofService
{
    public function __construct(
        private AppointmentWorkflow $workflow,
        private WhatsappService $whatsappService
    ) {
    }

    public function handleWhatsappProof(array $message, string $from): ?string
    {
        $mediaId = $message['image']['id'] ?? $message['document']['id'] ?? null;
        $mime = $message['image']['mime_type'] ?? $message['document']['mime_type'] ?? null;

        if (!$mediaId) {
            return null;
        }

        $path = $this->whatsappService->downloadMedia($mediaId, $mime);
        $appointment = $this->workflow->attachPaymentProof('whatsapp', $from, $path);

        if (!$appointment) {
            return null;
        }

        return $appointment->language ?? 'es';
    }
}
