<?php

namespace App\Traits;

use App\Entities\PersonalAccessToken;
use App\Auth\NewAccessToken;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Str;

trait HasApiTokens
{
    protected ?PersonalAccessToken $currentAccessToken = null;
    public function tokens()
    {
        $entityManager = app(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(PersonalAccessToken::class);
        
        return $repository->findBy(['tokenable_id' => $this->getId()]);
    }

    public function createToken(string $name, array $abilities = ['*'])
    {
        $entityManager = app(EntityManagerInterface::class);
        
        $plainTextToken = Str::random(40);
        
        $token = new PersonalAccessToken();
        $token->setTokenable($this);
        $token->setName($name);
        $token->setToken($plainTextToken);
        $token->setAbilities($abilities);
        
        $entityManager->persist($token);
        $entityManager->flush();
        
        return new NewAccessToken($token, $plainTextToken);
    }

    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentAccessToken;
    }

    public function withAccessToken(PersonalAccessToken $accessToken): self
    {
        $this->currentAccessToken = $accessToken;
        return $this;
    }

    public function deleteCurrentAccessToken()
    {
        if ($this->currentAccessToken) {
            $entityManager = app(EntityManagerInterface::class);
            $entityManager->remove($this->currentAccessToken);
            $entityManager->flush();
            $this->currentAccessToken = null;
            return true;
        }
        return false;
    }
}
