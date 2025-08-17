<?php

namespace App\Services;

use App\Entities\Transaction;
use App\Entities\User;
use App\Services\ExternalServices\AuthorizationService;
use App\Services\ExternalServices\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class TransferService
{
    protected EntityManagerInterface $entityManager;
    protected AuthorizationService $authorizationService;
    protected NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AuthorizationService $authorizationService,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->authorizationService = $authorizationService;
        $this->notificationService = $notificationService;
    }

    /**
     * Realizar transferência entre usuários
     */
    public function transfer(User $sender, User $recipient, float $amount, string $description = null): Transaction
    {
        // Validação de saldo
        if (!$sender->hasSufficientBalance($amount)) {
            throw new Exception('Saldo insuficiente para realizar a transferência');
        }

        // Verificar se o remetente pode enviar dinheiro
        if (!$sender->canSendMoney()) {
            throw new Exception('Usuário não pode enviar dinheiro');
        }

        $this->entityManager->beginTransaction();
        
        try {
            // Criar transação
            $transaction = new Transaction();
            $transaction->setPayer($sender);
            $transaction->setPayee($recipient);
            $transaction->setAmount($amount);
            $transaction->setType(Transaction::TYPE_TRANSFER);
            $transaction->setDescription($description ?? 'Transferência');

            // Consultar serviço autorizador externo
            if (!$this->authorizationService->authorize($sender, $recipient, $amount)) {
                throw new Exception('Transferência não autorizada pelo serviço externo');
            }

            // Atualizar saldos
            $sender->setBalance($sender->getBalance() - $amount);
            $recipient->setBalance($recipient->getBalance() + $amount);

            // Marcar transação como completada
            $transaction->setStatus(Transaction::STATUS_COMPLETED);

            // Persistir no banco
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            // Confirmar transação
            $this->entityManager->commit();

            // Enviar notificações assíncronas
            $this->sendNotifications($transaction);

            Log::info('Transferência realizada com sucesso', [
                'transaction_id' => $transaction->getId()->toString(),
                'sender_id' => $sender->getId()->toString(),
                'recipient_id' => $recipient->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (Exception $e) {
            $this->entityManager->rollback();
            
            // Marcar transação como falhou se ela foi criada
            if (isset($transaction)) {
                $transaction->setStatus(Transaction::STATUS_FAILED);
                $this->entityManager->persist($transaction);
                $this->entityManager->flush();
            }

            Log::error('Erro na transferência', [
                'sender_id' => $sender->getId()->toString(),
                'recipient_id' => $recipient->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Realizar depósito
     */
    public function deposit(User $user, float $amount): Transaction
    {
        $this->entityManager->beginTransaction();
        
        try {
            // Criar transação
            $transaction = new Transaction();
            $transaction->setPayee($user);
            $transaction->setAmount($amount);
            $transaction->setType(Transaction::TYPE_DEPOSIT);
            $transaction->setDescription('Depósito');

            // Atualizar saldo
            $user->setBalance($user->getBalance() + $amount);

            // Marcar transação como completada
            $transaction->setStatus(Transaction::STATUS_COMPLETED);

            // Persistir no banco
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            // Confirmar transação
            $this->entityManager->commit();

            Log::info('Depósito realizado com sucesso', [
                'transaction_id' => $transaction->getId()->toString(),
                'user_id' => $user->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (Exception $e) {
            $this->entityManager->rollback();
            
            Log::error('Erro no depósito', [
                'user_id' => $user->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Realizar saque
     */
    public function withdraw(User $user, float $amount): Transaction
    {
        // Validação de saldo
        if (!$user->hasSufficientBalance($amount)) {
            throw new Exception('Saldo insuficiente para realizar o saque');
        }

        $this->entityManager->beginTransaction();
        
        try {
            // Criar transação
            $transaction = new Transaction();
            $transaction->setPayer($user);
            $transaction->setAmount($amount);
            $transaction->setType(Transaction::TYPE_WITHDRAWAL);
            $transaction->setDescription('Saque');

            // Atualizar saldo
            $user->setBalance($user->getBalance() - $amount);

            // Marcar transação como completada
            $transaction->setStatus(Transaction::STATUS_COMPLETED);

            // Persistir no banco
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            // Confirmar transação
            $this->entityManager->commit();

            Log::info('Saque realizado com sucesso', [
                'transaction_id' => $transaction->getId()->toString(),
                'user_id' => $user->getId()->toString(),
                'amount' => $amount
            ]);

            return $transaction;

        } catch (Exception $e) {
            $this->entityManager->rollback();
            
            Log::error('Erro no saque', [
                'user_id' => $user->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Estornar uma transação
     */
    public function refund(Transaction $originalTransaction): Transaction
    {
        if (!$originalTransaction->isCompleted()) {
            throw new Exception('Apenas transações completadas podem ser estornadas');
        }

        if ($originalTransaction->getType() !== Transaction::TYPE_TRANSFER) {
            throw new Exception('Apenas transferências podem ser estornadas');
        }

        if ($originalTransaction->isRefunded()) {
            throw new Exception('Esta transação já foi estornada');
        }

        $payer = $originalTransaction->getPayer();
        $payee = $originalTransaction->getPayee();
        $amount = $originalTransaction->getAmount();

        // Verificar se o destinatário tem saldo suficiente para o estorno
        if (!$payee->hasSufficientBalance($amount)) {
            throw new Exception('Saldo insuficiente para realizar o estorno');
        }

        $this->entityManager->beginTransaction();
        
        try {
            // Criar transação de estorno
            $refundTransaction = new Transaction();
            $refundTransaction->setPayer($payee);
            $refundTransaction->setPayee($payer);
            $refundTransaction->setAmount($amount);
            $refundTransaction->setType(Transaction::TYPE_REFUND);
            $refundTransaction->setDescription('Estorno da transferência #' . $originalTransaction->getId()->toString());
            $refundTransaction->setOriginalTransactionId($originalTransaction->getId());

            // Atualizar saldos
            $payee->setBalance($payee->getBalance() - $amount);
            $payer->setBalance($payer->getBalance() + $amount);

            // Marcar transações
            $refundTransaction->setStatus(Transaction::STATUS_COMPLETED);
            $originalTransaction->setStatus(Transaction::STATUS_REFUNDED);

            // Persistir no banco
            $this->entityManager->persist($refundTransaction);
            $this->entityManager->flush();

            // Confirmar transação
            $this->entityManager->commit();

            // Enviar notificações
            $this->sendNotifications($refundTransaction);

            Log::info('Estorno realizado com sucesso', [
                'refund_transaction_id' => $refundTransaction->getId()->toString(),
                'original_transaction_id' => $originalTransaction->getId()->toString(),
                'amount' => $amount
            ]);

            return $refundTransaction;

        } catch (Exception $e) {
            $this->entityManager->rollback();
            
            Log::error('Erro no estorno', [
                'original_transaction_id' => $originalTransaction->getId()->toString(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Enviar notificações para os usuários envolvidos
     */
    protected function sendNotifications(Transaction $transaction): void
    {
        try {
            // Notificação para o remetente (se houver)
            if ($transaction->getPayer()) {
                $this->notificationService->sendTransactionNotification(
                    $transaction->getPayer(),
                    $transaction,
                    'sender'
                );
            }

            // Notificação para o destinatário
            $this->notificationService->sendTransactionNotification(
                $transaction->getPayee(),
                $transaction,
                'recipient'
            );
        } catch (Exception $e) {
            // Não falhar a transação por problemas de notificação
            Log::error('Erro ao enviar notificações', [
                'transaction_id' => $transaction->getId()->toString(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
