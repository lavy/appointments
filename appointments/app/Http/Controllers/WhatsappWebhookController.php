<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\ConversationState;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    private array $messages = [
        'es' => [
            'welcome' => "Hola, soy el asistente de turnos de :business.\nEscribe:\n1️⃣ para pedir un turno\n2️⃣ para ver/cancelar tu turno.",
            'ask_date' => '¿Para qué día quieres el turno? (formato: AAAA-MM-DD)',
            'ask_time' => 'Perfecto. ¿En qué horario prefieres? Ej: 10:00, 10:30, 11:00…',
            'ask_status' => 'Para ver o cancelar un turno, responde con 1 y agenda un nuevo turno. Por ahora el panel web es la forma recomendada.',
            'date_invalid' => 'Formato de fecha inválido. Usa AAAA-MM-DD.',
            'time_invalid' => 'Hora inválida. Usa el formato HH:MM (24 horas).',
            'missing_date' => 'No tengo registrada la fecha. Responde 1 para iniciar de nuevo.',
            'slot_taken' => 'Ese horario ya está reservado. Prueba con otro horario o fecha.',
            'confirmed' => 'Listo, tu turno quedó reservado para el :date a las :time a nombre de :name.',
        ],
        'en' => [
            'welcome' => "Hi! I'm the booking assistant for :business.\nType:\n1️⃣ to book an appointment\n2️⃣ to view/cancel your appointment.",
            'ask_date' => 'Which day would you like? (format: YYYY-MM-DD)',
            'ask_time' => 'Great. What time works for you? e.g., 10:00, 10:30, 11:00…',
            'ask_status' => 'To view or cancel an appointment, reply 1 and create a new booking. For now, use the web panel for status updates.',
            'date_invalid' => 'Invalid date format. Use YYYY-MM-DD.',
            'time_invalid' => 'Invalid time. Use HH:MM (24h).',
            'missing_date' => "I don't have the date saved. Reply 1 to start again.",
            'slot_taken' => 'That time is already booked. Try another time or day.',
            'confirmed' => 'Done! Your appointment is booked for :date at :time under :name.',
        ],
        'pt' => [
            'welcome' => "Olá, sou o assistente de agendamentos de :business.\nDigite:\n1️⃣ para marcar um horário\n2️⃣ para ver/cancelar seu horário.",
            'ask_date' => 'Para qual dia você quer o horário? (formato: AAAA-MM-DD)',
            'ask_time' => 'Perfeito. Qual horário prefere? Ex.: 10:00, 10:30, 11:00…',
            'ask_status' => 'Para ver ou cancelar um horário, responda 1 e crie um novo agendamento. Por enquanto, use o painel web para atualizar.',
            'date_invalid' => 'Formato de data inválido. Use AAAA-MM-DD.',
            'time_invalid' => 'Horário inválido. Use HH:MM (24h).',
            'missing_date' => 'Não tenho a data registrada. Responda 1 para começar de novo.',
            'slot_taken' => 'Esse horário já está reservado. Tente outro horário ou dia.',
            'confirmed' => 'Pronto! Seu horário está marcado para :date às :time em nome de :name.',
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

    public function __construct(private WhatsappService $whatsappService)
    {
    }

    public function verify(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if ($request->get('hub_verify_token') === $verifyToken) {
            return $request->get('hub_challenge');
        }

        return response('Error, invalid token', 403);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        [$from, $text, $displayName] = $this->extractMessage($payload);

        if (!$from || !$text) {
            return response()->json(['status' => 'ignored']);
        }

        $business = Business::first();

        if (!$business) {
            Log::warning('No business configured to handle WhatsApp messages');
            return response()->json(['status' => 'no_business']);
        }

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'customer_phone' => $from],
            ['current_step' => 'welcome', 'payload' => []]
        );

        $response = $this->respondForState($business, $state, $text, $displayName);

        if ($response) {
            $this->whatsappService->sendMessage($from, $response);
        }

        return response()->json(['status' => 'ok']);
    }

    private function extractMessage(array $payload): array
    {
        $changes = $payload['entry'][0]['changes'][0]['value'] ?? null;
        $messages = $changes['messages'][0] ?? null;

        if (!$messages) {
            return [null, null, null];
        }

        $from = $messages['from'] ?? null;
        $text = $messages['text']['body'] ?? '';
        $displayName = $changes['contacts'][0]['profile']['name'] ?? 'Cliente';

        return [$from, trim($text), $displayName];
    }

    private function respondForState(Business $business, ConversationState $state, string $text, string $displayName): ?string
    {
        $normalized = mb_strtolower($text);
        $language = $this->detectLanguage($normalized);

        if ($state->current_step === 'awaiting_date') {
            return $this->handleDateStep($business, $state, $normalized, $language);
        }

        if ($state->current_step === 'awaiting_time') {
            return $this->handleTimeStep($business, $state, $normalized, $displayName, $language);
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

        $state->update([
            'current_step' => 'awaiting_time',
            'payload' => ['date' => $text],
        ]);

        return $this->message('ask_time', $language, $business->name);
    }

    private function handleTimeStep(Business $business, ConversationState $state, string $text, string $displayName, string $language): string
    {
        if (!$this->isValidTime($text)) {
            return $this->message('time_invalid', $language, $business->name);
        }

        $date = $state->payload['date'] ?? null;

        if (!$date) {
            $state->update(['current_step' => 'welcome']);
            return $this->message('missing_date', $language, $business->name);
        }

        $slotTaken = Appointment::where('business_id', $business->id)
            ->whereDate('date', $date)
            ->whereTime('time', $text)
            ->where('status', '!=', 'canceled')
            ->exists();

        if ($slotTaken) {
            return $this->message('slot_taken', $language, $business->name);
        }

        Appointment::create([
            'business_id' => $business->id,
            'customer_name' => $displayName,
            'customer_phone' => $state->customer_phone,
            'date' => $date,
            'time' => $text,
            'status' => 'pending',
        ]);

        $state->update([
            'current_step' => 'welcome',
            'payload' => [],
        ]);

        return $this->message('confirmed', $language, $business->name, [
            ':date' => $date,
            ':time' => $text,
            ':name' => $displayName,
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
}
