<?php

namespace App\Auth\Providers;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class DoctrineUserProvider implements UserProvider
{
    protected EntityManagerInterface $entityManager;
    protected string $entityClass;

    public function __construct(EntityManagerInterface $entityManager, string $entityClass)
    {
        $this->entityManager = $entityManager;
        $this->entityClass = $entityClass;
    }

    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->entityManager->find($this->entityClass, $identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Para implementação futura do remember token
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Para implementação futura do remember token
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $repository = $this->entityManager->getRepository($this->entityClass);
        
        $queryBuilder = $repository->createQueryBuilder('u');
        
        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $queryBuilder->andWhere("u.{$key} = :{$key}")->setParameter($key, $value);
            }
        }
        
        try {
            return $queryBuilder->getQuery()->getOneOrNullResult();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        return Hash::check($credentials['password'], $user->getPassword());
    }

    /**
     * Rehash the user's password if required and supported.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Implementação opcional para rehash de senha
    }
}
