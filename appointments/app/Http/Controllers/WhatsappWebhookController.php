<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ConversationState;
use App\Services\BusinessResolver;
use App\Services\ConversationFlowService;
use App\Services\PaymentProofService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private WhatsappService $whatsappService,
        private ConversationFlowService $conversationFlow,
        private PaymentProofService $paymentProofs,
        private BusinessResolver $businessResolver
    )
    {
    }

    public function verify(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');

        $hubMode = $request->get('hub_mode');
        $hubToken = $request->get('hub_verify_token');

        $business = Business::where('whatsapp_verify_token', $hubToken)->first();

        if ($hubMode === 'subscribe' && ($hubToken === $verifyToken || $business)) {
            return $request->get('hub_challenge');
        }

        return response('Error, invalid token', 403);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        $phoneNumberId = $this->extractPhoneNumberId($payload);
        $business = $this->resolveBusiness($phoneNumberId);

        if (!$business) {
            Log::warning('No business configured to handle WhatsApp messages', [
                'phone_number_id' => $phoneNumberId,
            ]);
            return response()->json(['status' => 'no_business']);
        }

        if (!$this->verifySignature($request, $business->whatsapp_app_secret)) {
            Log::warning('Invalid WhatsApp signature', [
                'phone_number_id' => $phoneNumberId,
            ]);
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        [$from, $text, $displayName] = $this->extractMessage($payload);

        if (!$from || !$text) {
            if ($this->handleMedia($payload, $from ?? '', $business)) {
                return response()->json(['status' => 'ok']);
            }
            return response()->json(['status' => 'ignored']);
        }

        $state = ConversationState::firstOrCreate(
            ['business_id' => $business->id, 'contact_channel' => 'whatsapp', 'customer_phone' => $from],
            ['current_step' => 'welcome', 'payload' => []]
        );

        if ($this->handleMedia($payload, $from, $business)) {
            return response()->json(['status' => 'ok']);
        }

        $response = $this->conversationFlow->respond($business, $state, $text, $displayName, 'whatsapp');

        if ($response) {
            $this->whatsappService->sendMessage(
                $from,
                $response,
                $business->whatsapp_token,
                $business->whatsapp_phone_number_id
            );
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

    private function extractPhoneNumberId(array $payload): ?string
    {
        return $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;
    }

    private function resolveBusiness(?string $phoneNumberId): ?Business
    {
        if ($phoneNumberId) {
            $business = $this->businessResolver->resolveByWhatsapp($phoneNumberId);
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

    private function handleMedia(array $payload, string $from, Business $business): bool
    {
        $changes = $payload['entry'][0]['changes'][0]['value'] ?? null;
        $messages = $changes['messages'][0] ?? null;

        if (!$messages || !isset($messages['type']) || !in_array($messages['type'], ['image', 'document'], true)) {
            return false;
        }

        $language = $this->paymentProofs->handleWhatsappProof($business, $messages, $from);

        if ($language) {
            ConversationState::where('business_id', $business->id)
                ->where('contact_channel', 'whatsapp')
                ->where('customer_phone', $from)
                ->update(['current_step' => 'welcome', 'payload' => []]);
            $this->whatsappService->sendMessage(
                $from,
                $this->conversationFlow->paymentReceivedMessage($business, $language),
                $business->whatsapp_token,
                $business->whatsapp_phone_number_id
            );
            return true;
        }

        return false;
    }

}
