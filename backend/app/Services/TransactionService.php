<?php

namespace App\Services;

use App\Entities\Transaction;
use App\Entities\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;

class TransactionService
{
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private ExternalAuthorizationService $authorizationService;
    private NotificationService $notificationService;

    public function __construct(
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        ExternalAuthorizationService $authorizationService,
        NotificationService $notificationService
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->authorizationService = $authorizationService;
        $this->notificationService = $notificationService;
    }

    public function transfer(User $payer, User $payee, float $amount, ?string $description = null): Transaction
    {
        // Validações
        $this->validateTransfer($payer, $payee, $amount);

        // Início da transação de banco de dados
        $this->entityManager->beginTransaction();

        try {
            // Criar transação pendente
            $transaction = new Transaction();
            $transaction->setPayer($payer)
                ->setPayee($payee)
                ->setAmount($amount)
                ->setType(Transaction::TYPE_TRANSFER)
                ->setDescription($description)
                ->setStatus(Transaction::STATUS_PENDING);

            $this->transactionRepository->save($transaction);

            // Consultar serviço de autorização externa
            if (!$this->authorizationService->authorize()) {
                $transaction->setStatus(Transaction::STATUS_FAILED);
                $this->transactionRepository->save($transaction);
                $this->entityManager->commit();
                throw new \Exception('Transação não autorizada pelo serviço externo');
            }

            // Executar transferência
            $payer->subtractBalance($amount);
            $payee->addBalance($amount);

            $this->userRepository->save($payer);
            $this->userRepository->save($payee);

            $transaction->setStatus(Transaction::STATUS_COMPLETED);
            $this->transactionRepository->save($transaction);

            $this->entityManager->commit();

            // Enviar notificações (não crítico, não interrompe o fluxo)
            $this->sendNotifications($transaction);

            Log::info('Transfer completed successfully', [
                'transaction_id' => $transaction->getId()->toString(),
                'payer_id' => $payer->getId()->toString(),
                'payee_id' => $payee->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            Log::error('Transfer failed', [
                'payer_id' => $payer->getId()->toString(),
                'payee_id' => $payee->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function deposit(User $user, float $amount, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new \Exception('Valor do depósito deve ser maior que zero');
        }

        $this->entityManager->beginTransaction();

        try {
            $transaction = new Transaction();
            $transaction->setPayee($user)
                ->setAmount($amount)
                ->setType(Transaction::TYPE_DEPOSIT)
                ->setDescription($description)
                ->setStatus(Transaction::STATUS_COMPLETED);

            $user->addBalance($amount);

            $this->userRepository->save($user);
            $this->transactionRepository->save($transaction);

            $this->entityManager->commit();

            // Enviar notificação
            $this->notificationService->sendDepositNotification($user, $amount);

            Log::info('Deposit completed successfully', [
                'transaction_id' => $transaction->getId()->toString(),
                'user_id' => $user->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            Log::error('Deposit failed', [
                'user_id' => $user->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function withdraw(User $user, float $amount, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new \Exception('Valor do saque deve ser maior que zero');
        }

        if ($user->getBalance() < $amount) {
            throw new \Exception('Saldo insuficiente');
        }

        $this->entityManager->beginTransaction();

        try {
            $transaction = new Transaction();
            $transaction->setPayer($user)
                ->setPayee($user)
                ->setAmount($amount)
                ->setType(Transaction::TYPE_WITHDRAWAL)
                ->setDescription($description)
                ->setStatus(Transaction::STATUS_COMPLETED);

            $user->subtractBalance($amount);

            $this->userRepository->save($user);
            $this->transactionRepository->save($transaction);

            $this->entityManager->commit();

            // Enviar notificação
            $this->notificationService->sendWithdrawalNotification($user, $amount);

            Log::info('Withdrawal completed successfully', [
                'transaction_id' => $transaction->getId()->toString(),
                'user_id' => $user->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            Log::error('Withdrawal failed', [
                'user_id' => $user->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function reverse(UuidInterface $transactionId, User $requester): Transaction
    {
        $originalTransaction = $this->transactionRepository->findById($transactionId);

        if (!$originalTransaction) {
            throw new \Exception('Transação não encontrada');
        }

        if (!$originalTransaction->canBeReversed()) {
            throw new \Exception('Transação não pode ser estornada');
        }

        // Apenas o beneficiário pode estornar a transação
        if ($originalTransaction->getPayee()->getId()->toString() !== $requester->getId()->toString()) {
            throw new \Exception('Apenas o beneficiário pode estornar a transação');
        }

        $this->entityManager->beginTransaction();

        try {
            // Criar transação de estorno
            $reversalTransaction = new Transaction();
            $reversalTransaction->setPayer($originalTransaction->getPayee())
                ->setPayee($originalTransaction->getPayer())
                ->setAmount($originalTransaction->getAmount())
                ->setType(Transaction::TYPE_REVERSAL)
                ->setDescription('Estorno da transação ' . $originalTransaction->getId()->toString())
                ->setOriginalTransactionId($originalTransaction->getId())
                ->setStatus(Transaction::STATUS_COMPLETED);

            // Reverter os saldos
            $originalTransaction->getPayee()->subtractBalance($originalTransaction->getAmount());
            $originalTransaction->getPayer()->addBalance($originalTransaction->getAmount());

            // Marcar transação original como estornada
            $originalTransaction->setStatus(Transaction::STATUS_REVERSED);

            $this->userRepository->save($originalTransaction->getPayee());
            $this->userRepository->save($originalTransaction->getPayer());
            $this->transactionRepository->save($originalTransaction);
            $this->transactionRepository->save($reversalTransaction);

            $this->entityManager->commit();

            // Enviar notificação para quem recebeu o estorno
            $this->notificationService->sendReversalNotification(
                $originalTransaction->getPayer(),
                $originalTransaction->getAmount()
            );

            Log::info('Transaction reversed successfully', [
                'original_transaction_id' => $originalTransaction->getId()->toString(),
                'reversal_transaction_id' => $reversalTransaction->getId()->toString(),
                'requester_id' => $requester->getId()->toString(),
                'amount' => $originalTransaction->getAmount()
            ]);

            return $reversalTransaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();

            Log::error('Transaction reversal failed', [
                'transaction_id' => $transactionId->toString(),
                'requester_id' => $requester->getId()->toString(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function validateTransfer(User $payer, User $payee, float $amount): void
    {
        if ($amount <= 0) {
            throw new \Exception('Valor da transferência deve ser maior que zero');
        }

        if (!$payer->canSendMoney()) {
            throw new \Exception('Usuário não pode enviar dinheiro');
        }

        if ($payer->getBalance() < $amount) {
            throw new \Exception('Saldo insuficiente');
        }

        if ($payer->getId()->toString() === $payee->getId()->toString()) {
            throw new \Exception('Não é possível transferir para si mesmo');
        }
    }

    private function sendNotifications(Transaction $transaction): void
    {
        try {
            if ($transaction->getPayer()) {
                // Notificar pagador (quem enviou)
                $this->notificationService->sendTransferNotification(
                    $transaction->getPayer(),
                    $transaction->getAmount()
                );
            }

            // Notificar beneficiário (quem recebeu)
            $this->notificationService->sendTransferNotification(
                $transaction->getPayee(),
                $transaction->getAmount()
            );

        } catch (\Exception $e) {
            // Não interromper o fluxo por falha de notificação
            Log::warning('Failed to send notifications', [
                'transaction_id' => $transaction->getId()->toString(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
