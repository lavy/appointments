<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\ConversationFlowService;
use App\Services\MessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookMessengerWebhookController extends Controller
{
    public function __construct(
        private MessengerService $messengerService,
        private ConversationFlowService $conversationFlow
    ) {
    }

    public function verify(Request $request)
    {
        $verifyToken = config('services.messenger.verify_token');

        if ($request->get('hub_verify_token') === $verifyToken) {
            return $request->get('hub_challenge');
        }

        return response('Error, invalid token', 403);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        $messaging = $payload['entry'][0]['messaging'][0] ?? null;

        if (!$messaging || !isset($messaging['sender']['id'])) {
            return response()->json(['status' => 'ignored']);
        }

        $senderId = (string) $messaging['sender']['id'];
        $text = trim($messaging['message']['text'] ?? '');
        $displayName = $messaging['sender']['name'] ?? 'Cliente';

        if ($text === '') {
            return response()->json(['status' => 'ignored']);
        }

        $business = Business::first();

        if (!$business) {
            Log::warning('No business configured to handle Messenger messages');
            return response()->json(['status' => 'no_business']);
        }

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'customer_phone' => $senderId],
            ['current_step' => 'welcome', 'payload' => []]
        );

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName, 'messenger');

        if ($response) {
            $this->messengerService->sendMessage($senderId, $response);
        }

        return response()->json(['status' => 'ok']);
    }
}
