<?php

namespace App\Http\Controllers\Api;

use App\Entities\Transaction;
use App\Entities\User;
use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected EntityManagerInterface $entityManager;
    protected TransferService $transferService;

    public function __construct(EntityManagerInterface $entityManager, TransferService $transferService)
    {
        $this->entityManager = $entityManager;
        $this->transferService = $transferService;
    }

    /**
     * Listar transações do usuário
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $offset = ($page - 1) * $perPage;

        // Buscar transações onde o usuário é remetente ou destinatário
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
           ->from(Transaction::class, 't')
           ->where('t.payer = :user OR t.payee = :user')
           ->setParameter('user', $user)
           ->orderBy('t.createdAt', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($perPage);

        $transactions = $qb->getQuery()->getResult();

        // Contar total de transações
        $qbCount = $this->entityManager->createQueryBuilder();
        $qbCount->select('COUNT(t.id)')
                ->from(Transaction::class, 't')
                ->where('t.payer = :user OR t.payee = :user')
                ->setParameter('user', $user);

        $total = $qbCount->getQuery()->getSingleScalarResult();

        // Formatar dados das transações
        $formattedTransactions = array_map(function (Transaction $transaction) use ($user) {
            return $this->formatTransaction($transaction, $user);
        }, $transactions);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]
        ]);
    }

    /**
     * Obter detalhes de uma transação específica
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        $transaction = $this->entityManager->find(Transaction::class, $id);
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transação não encontrada'
            ], 404);
        }

        // Verificar se o usuário tem acesso a esta transação
        $hasAccess = ($transaction->getPayer() && $transaction->getPayer()->getId()->toString() === $user->getId()->toString()) ||
                     ($transaction->getPayee() && $transaction->getPayee()->getId()->toString() === $user->getId()->toString());

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction, $user)
        ]);
    }

    /**
     * Formatar dados da transação para resposta da API
     */
    protected function formatTransaction(Transaction $transaction, User $currentUser): array
    {
        $payer = $transaction->getPayer();
        $payee = $transaction->getPayee();
        $currentUserId = $currentUser->getId()->toString();

        // Determinar o papel do usuário atual na transação
        $userRole = 'unknown';
        if ($payer && $payer->getId()->toString() === $currentUserId) {
            $userRole = 'sender';
        } elseif ($payee && $payee->getId()->toString() === $currentUserId) {
            $userRole = 'recipient';
        }

        $data = [
            'id' => $transaction->getId()->toString(),
            'amount' => $transaction->getAmount(),
            'type' => $transaction->getType(),
            'status' => $transaction->getStatus(),
            'description' => $transaction->getDescription(),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->getUpdatedAt()->format('Y-m-d H:i:s'),
            'user_role' => $userRole,
            'can_refund' => $this->canUserRefundTransaction($transaction, $currentUser)
        ];

        // Adicionar informações do remetente (se houver)
        if ($payer) {
            $data['payer'] = [
                'id' => $payer->getId()->toString(),
                'name' => $payer->getName(),
                'type' => $payer->getType()
            ];
        }

        // Adicionar informações do destinatário (se houver)
        if ($payee) {
            $data['payee'] = [
                'id' => $payee->getId()->toString(),
                'name' => $payee->getName(),
                'type' => $payee->getType()
            ];
        }

        // Se há transação original (para estornos)
        if ($transaction->getOriginalTransactionId()) {
            $data['original_transaction_id'] = $transaction->getOriginalTransactionId()->toString();
        }

        return $data;
    }

    /**
     * Verificar se o usuário pode estornar a transação
     */
    protected function canUserRefundTransaction(Transaction $transaction, User $user): bool
    {
        // Apenas transferências completadas podem ser estornadas
        if ($transaction->getType() !== Transaction::TYPE_TRANSFER || 
            $transaction->getStatus() !== Transaction::STATUS_COMPLETED) {
            return false;
        }

        // Apenas quem recebeu pode estornar
        $payee = $transaction->getPayee();
        if (!$payee || $payee->getId()->toString() !== $user->getId()->toString()) {
            return false;
        }

        // Verificar se já foi estornada
        if ($transaction->getStatus() === Transaction::STATUS_REFUNDED) {
            return false;
        }

        // Verificar se tem saldo suficiente
        return $user->hasSufficientBalance($transaction->getAmount());
    }
}
