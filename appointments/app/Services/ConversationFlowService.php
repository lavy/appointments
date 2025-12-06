<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ConversationState;
use Carbon\Carbon;
use App\Services\AppointmentWorkflow;

class ConversationFlowService
{
    public function __construct(private AppointmentWorkflow $workflow)
    {
    }

    private array $messages = [
        'es' => [
            'welcome' => "Hola, soy el asistente de turnos de :business.\nEscribe:\n1️⃣ para pedir un turno\n2️⃣ para ver/cancelar tu turno.",
            'ask_date' => '¿Para qué día quieres el turno? (formato: AAAA-MM-DD)',
            'ask_time_options' => "Horarios disponibles para :date:\n:options\nResponde con el número de la opción que prefieras.",
            'payment_required' => "Tu turno quedó pre-reservado para el :date a las :time a nombre de :name.\n⌛ Se mantendrá reservado por :minutes minutos. Envía el comprobante de pago aquí para confirmarlo.\n:instructions",
            'payment_waiting' => "Estamos esperando tu comprobante para confirmar el turno reservado el :date a las :time.",
            'payment_received' => '📥 Recibimos tu comprobante. Un administrador revisará el pago y te confirmará el turno en breve.',
            'ask_status' => 'Para ver o cancelar un turno, responde con 1 y agenda un nuevo turno. Por ahora el panel web es la forma recomendada.',
            'date_invalid' => 'Formato de fecha inválido. Usa AAAA-MM-DD.',
            'time_invalid' => 'Hora inválida. Usa el formato HH:MM (24 horas).',
            'missing_date' => 'No tengo registrada la fecha. Responde 1 para iniciar de nuevo.',
            'slot_taken' => 'Ese horario ya está reservado. Prueba con otro horario o fecha.',
            'no_slots' => 'No hay horarios disponibles para :date. Elige otro día.',
            'invalid_option' => "Opción inválida. Elige un número de la lista.\n:options",
            'confirmed' => 'Listo, tu turno quedó confirmado para el :date a las :time a nombre de :name.',
        ],
        'en' => [
            'welcome' => "Hi! I'm the booking assistant for :business.\nType:\n1️⃣ to book an appointment\n2️⃣ to view/cancel your appointment.",
            'ask_date' => 'Which day would you like? (format: YYYY-MM-DD)',
            'ask_time_options' => "Available times for :date:\n:options\nReply with the number of your preferred slot.",
            'payment_required' => 'Your slot is pre-reserved for :date at :time under :name. ⌛ It will be held for :minutes minutes. Send your payment receipt here to confirm.\n:instructions',
            'payment_waiting' => 'We are waiting for your payment receipt to confirm the slot on :date at :time.',
            'payment_received' => '📥 We received your receipt. An admin will review and confirm your booking soon.',
            'ask_status' => 'To view or cancel an appointment, reply 1 and create a new booking. For now, use the web panel for status updates.',
            'date_invalid' => 'Invalid date format. Use YYYY-MM-DD.',
            'time_invalid' => 'Invalid time. Use HH:MM (24h).',
            'missing_date' => "I don't have the date saved. Reply 1 to start again.",
            'slot_taken' => 'That time is already booked. Try another time or day.',
            'no_slots' => 'No available times for :date. Please pick another day.',
            'invalid_option' => "Invalid option. Choose a number from the list.\n:options",
            'confirmed' => 'Done! Your appointment is confirmed for :date at :time under :name.',
        ],
        'pt' => [
            'welcome' => "Olá, sou o assistente de agendamentos de :business.\nDigite:\n1️⃣ para marcar um horário\n2️⃣ para ver/cancelar seu horário.",
            'ask_date' => 'Para qual dia você quer o horário? (formato: AAAA-MM-DD)',
            'ask_time_options' => "Horários disponíveis para :date:\n:options\nResponda com o número da opção que preferir.",
            'payment_required' => 'Seu horário ficou pré-reservado para :date às :time em nome de :name. ⌛ Ele será mantido por :minutes minutos. Envie o comprovante de pagamento aqui para confirmar.\n:instructions',
            'payment_waiting' => 'Estamos aguardando seu comprovante para confirmar o horário em :date às :time.',
            'payment_received' => '📥 Recebemos seu comprovante. Um administrador vai revisar e confirmar em breve.',
            'ask_status' => 'Para ver ou cancelar um horário, responda 1 e crie um novo agendamento. Por enquanto, use o painel web para atualizar.',
            'date_invalid' => 'Formato de data inválido. Use AAAA-MM-DD.',
            'time_invalid' => 'Horário inválido. Use HH:MM (24h).',
            'missing_date' => 'Não tenho a data registrada. Responda 1 para começar de novo.',
            'slot_taken' => 'Esse horário já está reservado. Tente outro horário ou dia.',
            'no_slots' => 'Não há horários disponíveis para :date. Escolha outro dia.',
            'invalid_option' => "Opção inválida. Escolha um número da lista.\n:options",
            'confirmed' => 'Pronto! Seu horário está confirmado para :date às :time em nome de :name.',
        ],
    ];

    private array $languageKeywords = [
        'en' => ['hello', 'hi', 'book', 'appointment', 'schedule', 'cancel'],
        'pt' => ['olá', 'ola', 'marcar', 'agendar', 'agendamento', 'cancelar', 'horário'],
        'es' => ['hola', 'turno', 'cita', 'agendar', 'cancelar', 'estado'],
    ];

    private array $startKeywords = [
        'en' => ['1', 'book', 'appointment', 'schedule', 'start'],
        'pt' => ['1', 'agendar', 'marcar', 'horário'],
        'es' => ['1', 'agendar', 'turno', 'cita'],
    ];

    private array $statusKeywords = [
        'en' => ['2', 'status', 'cancel', 'view'],
        'pt' => ['2', 'estado', 'cancelar', 'ver'],
        'es' => ['2', 'estado', 'cancelar'],
    ];

    public function respond(Business $business, ConversationState $state, string $text, string $displayName, string $channel): ?string
    {
        $normalized = mb_strtolower($text);
        $language = $this->detectLanguage($normalized);

        if ($state->current_step === 'awaiting_date') {
            return $this->handleDateStep($business, $state, $normalized, $language);
        }

        if ($state->current_step === 'awaiting_time') {
            return $this->handleTimeStep($business, $state, $normalized, $displayName, $language, $channel);
        }

        if ($state->current_step === 'awaiting_payment_proof') {
            $date = $state->payload['date'] ?? 'la fecha indicada';
            $time = $state->payload['time'] ?? 'hora indicada';

            return $this->message('payment_waiting', $language, $business->name, [
                ':date' => $date,
                ':time' => $time,
            ]);
        }

        if ($this->containsKeyword($normalized, $this->startKeywords[$language])) {
            $state->update([
                'current_step' => 'awaiting_date',
                'payload' => [],
            ]);

            return $this->message('ask_date', $language, $business->name);
        }

        if ($this->containsKeyword($normalized, $this->statusKeywords[$language])) {
            return $this->message('ask_status', $language, $business->name);
        }

        return $this->message('welcome', $language, $business->name);
    }

    private function handleDateStep(Business $business, ConversationState $state, string $text, string $language): string
    {
        if (!$this->isValidDate($text)) {
            return $this->message('date_invalid', $language, $business->name);
        }

        $availableSlots = $this->workflow->availableSlots($business, $text);

        if (empty($availableSlots)) {
            $state->update([
                'current_step' => 'awaiting_date',
                'payload' => [],
            ]);

            return $this->message('no_slots', $language, $business->name, [
                ':date' => $text,
            ]);
        }

        $state->update([
            'current_step' => 'awaiting_time',
            'payload' => ['date' => $text, 'slots' => $availableSlots],
        ]);

        return $this->message('ask_time_options', $language, $business->name, [
            ':date' => $text,
            ':options' => $this->formatOptionsList($availableSlots),
        ]);
    }

    private function handleTimeStep(Business $business, ConversationState $state, string $text, string $displayName, string $language, string $channel): string
    {
        $slots = $state->payload['slots'] ?? [];
        $selectedTime = null;

        if ($this->isNumericOption($text) && isset($slots[(int) $text - 1])) {
            $selectedTime = $slots[(int) $text - 1];
        } elseif ($this->isValidTime($text) && in_array($text, $slots, true)) {
            $selectedTime = $text;
        } else {
            return $this->message('invalid_option', $language, $business->name, [
                ':options' => $this->formatOptionsList($slots),
            ]);
        }

        $date = $state->payload['date'] ?? null;

        if (!$date) {
            $state->update(['current_step' => 'welcome']);
            return $this->message('missing_date', $language, $business->name);
        }

        if (!$this->workflow->slotIsAvailable($business, $date, $selectedTime)) {
            return $this->message('slot_taken', $language, $business->name);
        }

        $appointment = $this->workflow->createPreReservation($business, [
            'customer_name' => $displayName,
            'customer_phone' => $state->customer_phone,
            'date' => $date,
            'time' => $selectedTime,
            'language' => $language,
            'contact_channel' => $channel,
            'contact_identifier' => $state->customer_phone,
        ]);

        $state->update([
            'current_step' => 'awaiting_payment_proof',
            'payload' => [
                'date' => $date,
                'time' => $selectedTime,
                'appointment_id' => $appointment->id,
            ],
        ]);

        return $this->message('payment_required', $language, $business->name, [
            ':date' => $date,
            ':time' => $selectedTime,
            ':name' => $displayName,
            ':minutes' => AppointmentWorkflow::HOLD_MINUTES,
            ':instructions' => $business->payment_instructions ?? 'Envía el comprobante de pago por este chat para confirmar.',
        ]);
    }

    private function detectLanguage(string $text): string
    {
        foreach ($this->languageKeywords as $language => $keywords) {
            if ($this->containsKeyword($text, $keywords)) {
                return $language;
            }
        }

        return 'es';
    }

    private function containsKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($text === $keyword || str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function message(string $key, string $language, string $businessName, array $replacements = []): string
    {
        $message = $this->messages[$language][$key] ?? $this->messages['es'][$key];

        $allReplacements = array_merge([':business' => $businessName], $replacements);

        return strtr($message, $allReplacements);
    }

    private function isValidDate(string $value): bool
    {
        try {
            Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function isValidTime(string $value): bool
    {
        try {
            Carbon::createFromFormat('H:i', $value);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function formatOptionsList(array $slots): string
    {
        $lines = [];

        foreach ($slots as $index => $slot) {
            $lines[] = ($index + 1) . ') ' . $slot;
        }

        return implode("\n", $lines);
    }

    private function isNumericOption(string $text): bool
    {
        return ctype_digit($text) && (int) $text > 0;
    }

    public function paymentReceivedMessage(Business $business, string $language): string
    {
        return $this->message('payment_received', $language, $business->name);
    }
}
