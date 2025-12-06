<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\ConversationFlowService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegramService,
        private ConversationFlowService $conversationFlow
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        $message = $payload['message'] ?? null;
        if (!$message || !isset($message['chat']['id'])) {
            return response()->json(['status' => 'ignored']);
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $displayName = $message['from']['first_name'] ?? ($message['from']['username'] ?? 'Cliente');

        if ($text === '') {
            return response()->json(['status' => 'ignored']);
        }

        $business = Business::first();

        if (!$business) {
            Log::warning('No business configured to handle Telegram messages');
            return response()->json(['status' => 'no_business']);
        }

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'customer_phone' => $chatId],
            ['current_step' => 'welcome', 'payload' => []]
        );

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName);

        if ($response) {
            $this->telegramService->sendMessage($chatId, $response);
        }

        return response()->json(['status' => 'ok']);
    }
}
