<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

class AppointmentStatusNotificationService
{
    public function __construct(
        private WhatsappService $whatsappService,
        private TelegramService $telegramService,
        private MessengerService $messengerService
    ) {
    }

    public function sendPaymentDecision(Appointment $appointment): ?bool
    {
        $business = $appointment->business;
        $recipient = $appointment->contact_identifier ?: $appointment->customer_phone;

        if (!$business || !$recipient) {
            return null;
        }

        if ($appointment->status === 'confirmed') {
            $key = 'payment_approved';
            $fallbacks = $this->approvedMessages();
        } elseif ($appointment->status === 'expired') {
            $key = 'payment_rejected';
            $fallbacks = $this->rejectedMessages();
        } else {
            return null;
        }

        if ($appointment->contact_channel === 'panel') {
            return null;
        }

        $language = $appointment->language ?? 'es';
        $templates = $business->message_templates;
        $message = $this->resolveMessage(is_array($templates) ? $templates : [], $language, $key, $fallbacks);
        $message = strtr($message, [
            ':business' => $business->name,
            ':date' => $appointment->date->format('Y-m-d'),
            ':time' => $appointment->time->format('H:i'),
            ':name' => $appointment->customer_name,
            ':reason' => $appointment->payment_rejection_reason ?? '',
        ]);

        try {
            return match ($appointment->contact_channel) {
                'whatsapp' => $this->whatsappService->sendMessage(
                    $recipient,
                    $message,
                    $business->whatsapp_token,
                    $business->whatsapp_phone_number_id
                ),
                'telegram' => $this->telegramService->sendMessage(
                    $recipient,
                    $message,
                    $business->telegram_bot_token
                ),
                'messenger' => $this->messengerService->sendMessage(
                    $recipient,
                    $message,
                    $business->messenger_page_access_token,
                    $business->messenger_page_id
                ),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Appointment payment decision notification failed', [
                'appointment_id' => $appointment->id,
                'channel' => $appointment->contact_channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveMessage(array $templates, string $language, string $key, array $fallbacks): string
    {
        $localized = $templates[$language] ?? null;
        $spanish = $templates['es'] ?? null;
        $message = is_array($localized) ? ($localized[$key] ?? null) : null;

        if (!is_string($message)) {
            $message = is_array($spanish) ? ($spanish[$key] ?? null) : null;
        }

        if (is_string($message)) {
            return $message;
        }

        return $fallbacks[$language] ?? $fallbacks['es'];
    }

    private function approvedMessages(): array
    {
        return [
            'es' => '✅ Pago aprobado. Tu turno con :business quedó confirmado para el :date a las :time.',
            'en' => '✅ Payment approved. Your appointment with :business is confirmed for :date at :time.',
            'pt' => '✅ Pagamento aprovado. Seu horário com :business está confirmado para :date às :time.',
        ];
    }

    private function rejectedMessages(): array
    {
        return [
            'es' => 'No pudimos aprobar el comprobante de tu turno con :business para el :date a las :time.\nMotivo: :reason\nSi quieres reservar otro horario, responde 1.',
            'en' => 'We could not approve the payment receipt for your appointment with :business on :date at :time.\nReason: :reason\nReply 1 to book another time.',
            'pt' => 'Não foi possível aprovar o comprovante do seu horário com :business em :date às :time.\nMotivo: :reason\nResponda 1 para reservar outro horário.',
        ];
    }
}
