<?php

namespace App\Interfaces;

use App\Entities\User;

/**
 * Interface for external authorization services
 * 
 * This interface allows for different authorization implementations
 * following the Strategy Pattern for external payment validation
 */
interface AuthorizationServiceInterface
{
    /**
     * Authorize a transfer between users
     */
    public function authorize(User $sender, User $recipient, float $amount): bool;

    /**
     * Check service health/availability
     */
    public function isAvailable(): bool;
}
