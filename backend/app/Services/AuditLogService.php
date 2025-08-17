<?php

namespace App\Services;

use App\Entities\Transaction;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling audit logging of financial operations
 * 
 * This service implements proper audit trails for compliance and monitoring
 * following banking industry best practices for transaction logging.
 */
class AuditLogService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Log transfer operation with all relevant details
     */
    public function logTransfer(Transaction $transaction): void
    {
        $logData = [
            'event_type' => 'transfer',
            'transaction_id' => $transaction->getId()->toString(),
            'payer_id' => $transaction->getPayer()?->getId()->toString(),
            'payee_id' => $transaction->getPayee()?->getId()->toString(),
            'amount' => $transaction->getAmount(),
            'status' => $transaction->getStatus(),
            'description' => $transaction->getDescription(),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId()
        ];

        Log::channel('audit')->info('Transfer operation', $logData);
    }

    /**
     * Log deposit operation
     */
    public function logDeposit(Transaction $transaction): void
    {
        $logData = [
            'event_type' => 'deposit',
            'transaction_id' => $transaction->getId()->toString(),
            'user_id' => $transaction->getPayee()?->getId()->toString(),
            'amount' => $transaction->getAmount(),
            'status' => $transaction->getStatus(),
            'description' => $transaction->getDescription(),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        Log::channel('audit')->info('Deposit operation', $logData);
    }

    /**
     * Log withdrawal operation
     */
    public function logWithdrawal(Transaction $transaction): void
    {
        $logData = [
            'event_type' => 'withdrawal',
            'transaction_id' => $transaction->getId()->toString(),
            'user_id' => $transaction->getPayer()?->getId()->toString(),
            'amount' => $transaction->getAmount(),
            'status' => $transaction->getStatus(),
            'description' => $transaction->getDescription(),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        Log::channel('audit')->info('Withdrawal operation', $logData);
    }

    /**
     * Log refund operation
     */
    public function logRefund(Transaction $originalTransaction, Transaction $refundTransaction): void
    {
        $logData = [
            'event_type' => 'refund',
            'original_transaction_id' => $originalTransaction->getId()->toString(),
            'refund_transaction_id' => $refundTransaction->getId()->toString(),
            'amount' => $refundTransaction->getAmount(),
            'refunded_by' => request()->user()?->getId()->toString(),
            'reason' => $refundTransaction->getDescription(),
            'created_at' => $refundTransaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        Log::channel('audit')->warning('Refund operation', $logData);
    }

    /**
     * Log authentication events
     */
    public function logAuthentication(User $user, string $event, bool $success = true): void
    {
        $logData = [
            'event_type' => 'authentication',
            'sub_event' => $event,
            'user_id' => $user->getId()->toString(),
            'user_email' => $user->getEmail(),
            'user_type' => $user->getType(),
            'success' => $success,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];

        if ($success) {
            Log::channel('audit')->info('Authentication event', $logData);
        } else {
            Log::channel('audit')->warning('Authentication failure', $logData);
        }
    }

    /**
     * Log security violations
     */
    public function logSecurityViolation(string $violation, array $context = []): void
    {
        $logData = array_merge([
            'event_type' => 'security_violation',
            'violation' => $violation,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => request()->user()?->getId()->toString(),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ], $context);

        Log::channel('audit')->error('Security violation detected', $logData);
    }

    /**
     * Log balance changes for tracking
     */
    public function logBalanceChange(User $user, float $previousBalance, float $newBalance, string $reason): void
    {
        $logData = [
            'event_type' => 'balance_change',
            'user_id' => $user->getId()->toString(),
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'difference' => $newBalance - $previousBalance,
            'reason' => $reason,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];

        Log::channel('audit')->info('Balance change', $logData);
    }

    /**
     * Log external service calls for monitoring
     */
    public function logExternalServiceCall(string $service, string $endpoint, bool $success, ?string $response = null): void
    {
        $logData = [
            'event_type' => 'external_service_call',
            'service' => $service,
            'endpoint' => $endpoint,
            'success' => $success,
            'response_excerpt' => $response ? substr($response, 0, 200) : null,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];

        if ($success) {
            Log::channel('external')->info('External service call', $logData);
        } else {
            Log::channel('external')->error('External service failure', $logData);
        }
    }
}
