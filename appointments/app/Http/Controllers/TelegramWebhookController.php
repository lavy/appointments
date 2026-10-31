<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\BusinessResolver;
use App\Services\ConversationFlowService;
use App\Services\PaymentProofService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegramService,
        private ConversationFlowService $conversationFlow,
        private PaymentProofService $paymentProofs,
        private BusinessResolver $businessResolver
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
        $business = $this->resolveBusiness($request);

        if (!$business) {
            Log::warning('No business configured to handle Telegram messages');
            return response()->json(['status' => 'no_business']);
        }

        if ($this->handleMedia($message, $chatId, $business)) {
            return response()->json(['status' => 'ok']);
        }

        $text = trim($message['text'] ?? '');
        if ($text === '') {
            return response()->json(['status' => 'ignored']);
        }

        $displayName = $message['from']['first_name'] ?? ($message['from']['username'] ?? 'Cliente');

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'contact_channel' => 'telegram', 'customer_phone' => $chatId],
            ['current_step' => 'welcome', 'payload' => []]
        );

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName, 'telegram');

        if ($response) {
            $this->telegramService->sendMessage($chatId, $response, $business->telegram_bot_token);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleMedia(array $message, string $chatId, Business $business): bool
    {
        $language = $this->paymentProofs->handleTelegramProof($business, $message, $chatId);

        if (!$language) {
            return false;
        }

        ConversationState::where('business_id', $business->id)
            ->where('contact_channel', 'telegram')
            ->where('customer_phone', $chatId)
            ->update(['current_step' => 'welcome', 'payload' => []]);

        $this->telegramService->sendMessage(
            $chatId,
            $this->conversationFlow->paymentReceivedMessage($business, $language),
            $business->telegram_bot_token
        );

        return true;
    }

    private function resolveBusiness(Request $request): ?Business
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secret) {
            $business = Business::where('telegram_secret_token', $secret)->first();
            if ($business) {
                return $business;
            }
        }

        $businesses = Business::limit(2)->get();
        if ($businesses->count() > 1 && !$secret) {
            return null;
        }

        $configuredToken = config('services.telegram.bot_token');
        if ($configuredToken) {
            $business = $this->businessResolver->resolveByTelegramBotToken($configuredToken);
            if ($business) {
                return $business;
            }
        }

        if ($businesses->count() === 1) {
            return $businesses->first();
        }

        return null;
    }
}
