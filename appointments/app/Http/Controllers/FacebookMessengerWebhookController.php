<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\BusinessResolver;
use App\Services\ConversationFlowService;
use App\Services\MessengerService;
use App\Services\PaymentProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookMessengerWebhookController extends Controller
{
    public function __construct(
        private MessengerService $messengerService,
        private ConversationFlowService $conversationFlow,
        private PaymentProofService $paymentProofs,
        private BusinessResolver $businessResolver
    ) {
    }

    public function verify(Request $request)
    {
        $verifyToken = config('services.messenger.verify_token');
        $hubMode = $request->get('hub_mode');
        $hubToken = $request->get('hub_verify_token');

        $business = Business::where('messenger_verify_token', $hubToken)->first();

        if ($hubMode === 'subscribe' && ($hubToken === $verifyToken || $business)) {
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
        $incomingMessage = $messaging['message'] ?? [];
        $text = trim($incomingMessage['text'] ?? '');
        $displayName = $messaging['sender']['name'] ?? 'Cliente';

        if ($text === '' && empty($incomingMessage['attachments'])) {
            return response()->json(['status' => 'ignored']);
        }

        $business = $this->resolveBusiness($payload);

        if (!$business) {
            Log::warning('No business configured to handle Messenger messages');
            return response()->json(['status' => 'no_business']);
        }

        if (!$this->verifySignature($request, $business->messenger_app_secret)) {
            Log::warning('Invalid Messenger signature', [
                'page_id' => $payload['entry'][0]['id'] ?? null,
            ]);
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        if ($this->handleMedia($incomingMessage, $senderId, $business)) {
            return response()->json(['status' => 'ok']);
        }

        if ($text === '') {
            return response()->json(['status' => 'ignored']);
        }

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'contact_channel' => 'messenger', 'customer_phone' => $senderId],
            ['current_step' => 'welcome', 'payload' => []]
        );

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName, 'messenger');

        if ($response) {
            $this->messengerService->sendMessage(
                $senderId,
                $response,
                $business->messenger_page_access_token,
                $business->messenger_page_id
            );
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleMedia(array $message, string $senderId, Business $business): bool
    {
        $language = $this->paymentProofs->handleMessengerProof($business, $message, $senderId);

        if (!$language) {
            return false;
        }

        ConversationState::where('business_id', $business->id)
            ->where('contact_channel', 'messenger')
            ->where('customer_phone', $senderId)
            ->update(['current_step' => 'welcome', 'payload' => []]);

        $this->messengerService->sendMessage(
            $senderId,
            $this->conversationFlow->paymentReceivedMessage($business, $language),
            $business->messenger_page_access_token,
            $business->messenger_page_id
        );

        return true;
    }

    private function resolveBusiness(array $payload): ?Business
    {
        $pageId = $payload['entry'][0]['id'] ?? null;
        if ($pageId) {
            $business = $this->businessResolver->resolveByMessengerPageId($pageId);
            if ($business) {
                return $business;
            }
        }

        $businesses = Business::limit(2)->get();
        if ($businesses->count() === 1) {
            return $businesses->first();
        }

        return null;
    }

    private function verifySignature(Request $request, ?string $secret): bool
    {
        if (!$secret) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (!$signature || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $payload = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
