<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\ConversationFlowService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private WhatsappService $whatsappService,
        private ConversationFlowService $conversationFlow
    )
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

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName, 'whatsapp');

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

}
