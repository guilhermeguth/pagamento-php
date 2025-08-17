<?php

namespace App\Services\ExternalServices;

use App\Entities\Transaction;
use App\Entities\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected string $notificationUrl;
    protected int $timeout;

    public function __construct()
    {
        // URL do mock do serviço de notificações
        $this->notificationUrl = 'https://util.devi.tools/api/v1/notify';
        $this->timeout = 5; // 5 segundos de timeout
    }

    /**
     * Enviar notificação de transação para o usuário
     */
    public function sendTransactionNotification(User $user, Transaction $transaction, string $role): bool
    {
        try {
            $message = $this->buildMessage($transaction, $role);

            Log::info('Enviando notificação', [
                'user_id' => $user->getId()->toString(),
                'transaction_id' => $transaction->getId()->toString(),
                'role' => $role,
                'message' => $message
            ]);

            $response = Http::timeout($this->timeout)->post($this->notificationUrl, [
                'email' => $user->getEmail(),
                'message' => $message
            ]);

            if (!$response->successful()) {
                Log::warning('Serviço de notificação indisponível', [
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                    'user_id' => $user->getId()->toString()
                ]);
                return false;
            }

            $data = $response->json();
            
            // O mock retorna {"status": "success"} ou {"status": "fail"}
            $sent = isset($data['status']) && $data['status'] === 'success';

            Log::info('Resposta do serviço de notificação', [
                'sent' => $sent,
                'response' => $data,
                'user_id' => $user->getId()->toString()
            ]);

            return $sent;

        } catch (Exception $e) {
            Log::error('Erro ao enviar notificação', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()->toString(),
                'transaction_id' => $transaction->getId()->toString()
            ]);

            return false;
        }
    }

    /**
     * Construir mensagem da notificação baseada no tipo de transação e role
     */
    protected function buildMessage(Transaction $transaction, string $role): string
    {
        $amount = number_format($transaction->getAmount(), 2, ',', '.');
        
        switch ($transaction->getType()) {
            case Transaction::TYPE_TRANSFER:
                if ($role === 'sender') {
                    $recipientName = $transaction->getPayee()->getName();
                    return "Você enviou R$ {$amount} para {$recipientName}.";
                } else {
                    $senderName = $transaction->getPayer()->getName();
                    return "Você recebeu R$ {$amount} de {$senderName}.";
                }
                
            case Transaction::TYPE_DEPOSIT:
                return "Depósito de R$ {$amount} realizado com sucesso.";
                
            case Transaction::TYPE_WITHDRAWAL:
                return "Saque de R$ {$amount} realizado com sucesso.";
                
            case Transaction::TYPE_REFUND:
                if ($role === 'sender') {
                    $recipientName = $transaction->getPayee()->getName();
                    return "Estorno de R$ {$amount} enviado para {$recipientName}.";
                } else {
                    return "Você recebeu um estorno de R$ {$amount}.";
                }
                
            default:
                return "Transação de R$ {$amount} processada.";
        }
    }

    /**
     * Verificar se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->notificationUrl);
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}
