<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Business;
use App\Services\MessengerService;
use App\Services\TelegramService;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send appointment reminders two days before through the original contact channel';

    public function __construct(
        private WhatsappService $whatsappService,
        private TelegramService $telegramService,
        private MessengerService $messengerService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $businesses = Business::with('appointments')->get();

        foreach ($businesses as $business) {
            $targetDate = Carbon::now($business->timezone)->addDays(2)->toDateString();

            $appointments = $business->appointments()
                ->whereDate('date', $targetDate)
                ->whereIn('status', ['confirmed'])
                ->whereNull('reminder_sent_at')
                ->get();

            foreach ($appointments as $appointment) {
                $message = $this->reminderMessage($appointment, $business);
                $sent = $this->dispatchReminder($appointment, $message);

                if ($sent) {
                    $appointment->forceFill(['reminder_sent_at' => now()])->save();
                }
            }
        }

        return Command::SUCCESS;
    }

    private function reminderMessage(Appointment $appointment, Business $business): string
    {
        $templates = [
            'es' => 'Recordatorio: tienes un turno con :business el :date a las :time. Responde si necesitas cambiarlo.',
            'en' => 'Reminder: you have an appointment with :business on :date at :time. Reply here if you need to reschedule.',
            'pt' => 'Lembrete: você tem um horário com :business em :date às :time. Responda aqui se precisar reagendar.',
        ];

        $language = $appointment->language ?? 'es';
        $template = $templates[$language] ?? $templates['es'];

        return strtr($template, [
            ':business' => $business->name,
            ':date' => Carbon::parse($appointment->date)->format('Y-m-d'),
            ':time' => Carbon::parse($appointment->time)->format('H:i'),
        ]);
    }

    private function dispatchReminder(Appointment $appointment, string $message): bool
    {
        $channel = $appointment->contact_channel;
        $recipient = $appointment->contact_identifier ?? $appointment->customer_phone;

        if (!$channel || !$recipient) {
            Log::warning('Cannot send reminder without channel or recipient', [
                'appointment_id' => $appointment->id,
            ]);

            return false;
        }

        return match ($channel) {
            'whatsapp' => $this->sendWithHandler(fn () => $this->whatsappService->sendMessage($recipient, $message)),
            'telegram' => $this->sendWithHandler(fn () => $this->telegramService->sendMessage($recipient, $message)),
            'messenger' => $this->sendWithHandler(fn () => $this->messengerService->sendMessage($recipient, $message)),
            default => false,
        };
    }

    private function sendWithHandler(callable $callback): bool
    {
        try {
            $callback();
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send reminder', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
