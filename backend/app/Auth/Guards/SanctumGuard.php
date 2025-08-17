<?php

namespace App\Auth\Guards;

use App\Entities\PersonalAccessToken;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class SanctumGuard implements Guard
{
    protected ?object $user = null;
    protected UserProvider $provider;
    protected Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?object
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getBearerToken();
        
        if (!$token) {
            return null;
        }

        // Buscar o token no banco de dados
        $entityManager = app(EntityManagerInterface::class);
        $tokenRepository = $entityManager->getRepository(PersonalAccessToken::class);
        
        $accessToken = $tokenRepository->findOneBy(['token' => hash('sha256', $token)]);
        
        if (!$accessToken) {
            return null;
        }

        // Verificar se o token não expirou
        if ($accessToken->getExpiresAt() && $accessToken->getExpiresAt() < new \DateTime()) {
            return null;
        }

        // Buscar o usuário
        $this->user = $this->provider->retrieveById($accessToken->getTokenableId());
        
        if ($this->user) {
            $this->user->withAccessToken($accessToken);
        }
        
        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id(): mixed
    {
        if ($user = $this->user()) {
            return $user->getAuthIdentifier();
        }

        return null;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user) {
            return $this->provider->validateCredentials($user, $credentials);
        }

        return false;
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     */
    public function attempt(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->setUser($user);
            return true;
        }

        return false;
    }

    /**
     * Set the current user.
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * Determine if the guard has a user instance.
     */
    public function hasUser(): bool
    {
        return !is_null($this->user);
    }

    /**
     * Get the bearer token from the request header.
     */
    protected function getBearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');
        
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
