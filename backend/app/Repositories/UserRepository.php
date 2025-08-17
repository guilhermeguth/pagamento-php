<?php

namespace App\Repositories;

use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\UuidInterface;

class UserRepository
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(User::class);
    }

    public function findById(UuidInterface $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email]);
    }

    public function findByDocument(string $document): ?User
    {
        return $this->repository->findOneBy(['document' => $document]);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function save(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function existsByEmail(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function existsByDocument(string $document): bool
    {
        return $this->findByDocument($document) !== null;
    }

    public function findCommonUsers(): array
    {
        return $this->repository->findBy(['type' => User::TYPE_COMMON]);
    }

    public function findMerchants(): array
    {
        return $this->repository->findBy(['type' => User::TYPE_MERCHANT]);
    }
}
