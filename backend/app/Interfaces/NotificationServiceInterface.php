<?php

namespace App\Interfaces;

use App\Entities\User;

/**
 * Interface for external notification services
 * 
 * This interface allows for different notification implementations
 * (email, SMS, push notifications, etc.) following the Strategy Pattern
 */
interface NotificationServiceInterface
{
    /**
     * Send transfer notification to recipient
     */
    public function sendTransferNotification(User $recipient, float $amount): bool;

    /**
     * Send deposit notification to user
     */
    public function sendDepositNotification(User $recipient, float $amount): bool;

    /**
     * Send withdrawal notification to user
     */
    public function sendWithdrawalNotification(User $recipient, float $amount): bool;

    /**
     * Send refund/reversal notification to user
     */
    public function sendReversalNotification(User $recipient, float $amount): bool;

    /**
     * Send generic notification with custom message
     */
    public function sendNotification(User $user, string $message): bool;
}
