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

        if ($state->current_step === 'awaiting_date') {
            return $this->handleDateStep($business, $state, $normalized);
        }

        if ($state->current_step === 'awaiting_time') {
            return $this->handleTimeStep($business, $state, $normalized, $displayName);
        }

        if (in_array($normalized, ['1', 'agendar', 'turno', 'cita'], true)) {
            $state->update([
                'current_step' => 'awaiting_date',
                'payload' => [],
            ]);

            return "¿Para qué día quieres el turno? (formato: AAAA-MM-DD)";
        }

        if (in_array($normalized, ['2', 'estado', 'cancelar'], true)) {
            return "Para ver o cancelar un turno, responde con 1 y agenda un nuevo turno. Por ahora el panel web es la forma recomendada.";
        }

        return "Hola, soy el asistente de turnos de {$business->name}.\nEscribe:\n1️⃣ para pedir un turno\n2️⃣ para ver/cancelar tu turno.";
    }

    private function handleDateStep(Business $business, ConversationState $state, string $text): string
    {
        if (!$this->isValidDate($text)) {
            return 'Formato de fecha inválido. Usa AAAA-MM-DD.';
        }

        $state->update([
            'current_step' => 'awaiting_time',
            'payload' => ['date' => $text],
        ]);

        return 'Perfecto. ¿En qué horario prefieres? Ej: 10:00, 10:30, 11:00…';
    }

    private function handleTimeStep(Business $business, ConversationState $state, string $text, string $displayName): string
    {
        if (!$this->isValidTime($text)) {
            return 'Hora inválida. Usa el formato HH:MM (24 horas).';
        }

        $date = $state->payload['date'] ?? null;

        if (!$date) {
            $state->update(['current_step' => 'welcome']);
            return 'No tengo registrada la fecha. Responde 1 para iniciar de nuevo.';
        }

        $slotTaken = Appointment::where('business_id', $business->id)
            ->whereDate('date', $date)
            ->whereTime('time', $text)
            ->where('status', '!=', 'canceled')
            ->exists();

        if ($slotTaken) {
            return 'Ese horario ya está reservado. Prueba con otro horario o fecha.';
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

        return "Listo, tu turno quedó reservado para el {$date} a las {$text} a nombre de {$displayName}.";
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
