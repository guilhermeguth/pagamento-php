<?php

namespace App\Repositories;

use App\Entities\Transaction;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\UuidInterface;

class TransactionRepository
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(Transaction::class);
    }

    public function findById(UuidInterface $id): ?Transaction
    {
        return $this->repository->find($id);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findByUser(User $user): array
    {
        return $this->repository->createQueryBuilder('t')
            ->where('t.payer = :user OR t.payee = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPayer(User $payer): array
    {
        return $this->repository->findBy(['payer' => $payer], ['createdAt' => 'DESC']);
    }

    public function findByPayee(User $payee): array
    {
        return $this->repository->findBy(['payee' => $payee], ['createdAt' => 'DESC']);
    }

    public function findByStatus(string $status): array
    {
        return $this->repository->findBy(['status' => $status]);
    }

    public function findPendingTransactions(): array
    {
        return $this->findByStatus(Transaction::STATUS_PENDING);
    }

    public function findCompletedTransactions(): array
    {
        return $this->findByStatus(Transaction::STATUS_COMPLETED);
    }

    public function save(Transaction $transaction): Transaction
    {
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
    }

    public function getUserBalance(User $user): float
    {
        $received = (float) $this->repository->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.payee = :user')
            ->andWhere('t.status = :status')
            ->andWhere('t.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('status', Transaction::STATUS_COMPLETED)
            ->setParameter('types', [Transaction::TYPE_DEPOSIT, Transaction::TYPE_TRANSFER])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $sent = (float) $this->repository->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.payer = :user')
            ->andWhere('t.status = :status')
            ->andWhere('t.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('status', Transaction::STATUS_COMPLETED)
            ->setParameter('types', [Transaction::TYPE_TRANSFER, Transaction::TYPE_WITHDRAWAL])
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $received - $sent;
    }
}
