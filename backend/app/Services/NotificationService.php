<?php

namespace App\Services;

use App\Entities\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private string $notificationUrl;

    public function __construct()
    {
        $this->notificationUrl = config('services.notification.url', 'https://util.devi.tools/api/v1/notify');
    }

    public function sendTransferNotification(User $recipient, float $amount): bool
    {
        return $this->sendNotification($recipient, "Você recebeu uma transferência de R$ " . number_format($amount, 2, ',', '.'));
    }

    public function sendDepositNotification(User $recipient, float $amount): bool
    {
        return $this->sendNotification($recipient, "Você fez um depósito de R$ " . number_format($amount, 2, ',', '.'));
    }

    public function sendWithdrawalNotification(User $recipient, float $amount): bool
    {
        return $this->sendNotification($recipient, "Você fez um saque de R$ " . number_format($amount, 2, ',', '.'));
    }

    public function sendReversalNotification(User $recipient, float $amount): bool
    {
        return $this->sendNotification($recipient, "Uma transação de R$ " . number_format($amount, 2, ',', '.') . " foi estornada para sua conta");
    }

    private function sendNotification(User $user, string $message): bool
    {
        try {
            $response = Http::timeout(10)->post($this->notificationUrl, [
                'email' => $user->getEmail(),
                'message' => $message
            ]);

            if (!$response->successful()) {
                Log::warning('Notification service returned non-successful response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $user->getId()->toString()
                ]);
                return false;
            }

            $data = $response->json();

            // O mock pode retornar diferentes respostas
            return isset($data['message']);

        } catch (\Exception $e) {
            Log::error('Notification service failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()->toString(),
                'message' => $message,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
