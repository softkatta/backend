<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Events\InAppNotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        private readonly IntegrationCredentialService $integrations,
        private readonly SmtpMailService $smtpMail,
        private readonly MailTemplateService $templates,
    ) {
    }

    /**
     * @return array<int, NotificationChannel>
     */
    public static function allChannels(): array
    {
        return [
            NotificationChannel::Email,
            NotificationChannel::Whatsapp,
            NotificationChannel::InApp,
        ];
    }

    /**
     * @param  array<int, NotificationChannel>  $channels
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $emailDetails
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        array $channels = [NotificationChannel::InApp],
        array $data = [],
        array $emailDetails = [],
    ): void {
        foreach ($channels as $channel) {
            match ($channel) {
                NotificationChannel::Email => $this->sendEmail($user, $title, $message, $emailDetails),
                NotificationChannel::Whatsapp => $this->sendWhatsapp($user, $message),
                NotificationChannel::InApp => $this->createInApp($user, $type, $title, $message, $data),
            };
        }
    }

    /**
     * @param  array<string, string>  $details
     */
    protected function sendEmail(User $user, string $title, string $message, array $details = []): void
    {
        try {
            $this->smtpMail->send(
                $user->email,
                $this->templates->formatSubject($title),
                $message,
                $title,
                $details,
            );
        } catch (\Throwable $e) {
            Log::warning('SoftKatta email notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendWhatsapp(User $user, string $message): void
    {
        $whatsapp = $this->integrations->whatsapp();

        if (! $whatsapp || ! $user->phone) {
            Log::info('SoftKatta WhatsApp notification skipped', [
                'user_id' => $user->id,
                'configured' => $whatsapp !== null,
                'has_phone' => (bool) $user->phone,
            ]);

            return;
        }

        $phone = preg_replace('/\D+/', '', $user->phone) ?? '';

        if ($phone === '') {
            return;
        }

        try {
            $response = Http::withToken($whatsapp['access_token'])
                ->post("https://graph.facebook.com/{$whatsapp['api_version']}/{$whatsapp['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if (! $response->successful()) {
                Log::warning('SoftKatta WhatsApp notification failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SoftKatta WhatsApp notification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createInApp(User $user, string $type, string $title, string $message, array $data): Notification
    {
        $this->integrations->applyBroadcastingConfig();

        $notification = Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'channel' => NotificationChannel::InApp,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        if ($this->integrations->isPusherConfigured()) {
            event(new InAppNotificationCreated($notification));
        }

        return $notification;
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->update(['read_at' => now()]);

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
