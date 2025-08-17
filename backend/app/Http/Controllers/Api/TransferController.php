<?php

namespace App\Http\Controllers\Api;

use App\Entities\Transaction;
use App\Entities\User;
use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    protected EntityManagerInterface $entityManager;
    protected TransferService $transferService;

    public function __construct(EntityManagerInterface $entityManager, TransferService $transferService)
    {
        $this->entityManager = $entityManager;
        $this->transferService = $transferService;
    }

    /**
     * Realizar transferência entre usuários
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient_id' => 'required|string|uuid',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sender = $request->user();
            
            // Verificar se o usuário pode enviar dinheiro
            if (!$sender->canSendMoney()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lojistas não podem enviar dinheiro'
                ], 403);
            }

            // Buscar destinatário
            $recipient = $this->entityManager->find(User::class, $request->recipient_id);
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destinatário não encontrado'
                ], 404);
            }

            // Não permitir transferência para si mesmo
            if ($sender->getId()->toString() === $recipient->getId()->toString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível transferir para si mesmo'
                ], 400);
            }

            // Realizar transferência
            $transaction = $this->transferService->transfer(
                $sender,
                $recipient,
                $request->amount,
                $request->description ?? 'Transferência'
            );

            return response()->json([
                'success' => true,
                'message' => 'Transferência realizada com sucesso',
                'data' => [
                    'transaction_id' => $transaction->getId()->toString(),
                    'amount' => $transaction->getAmount(),
                    'sender' => [
                        'id' => $sender->getId()->toString(),
                        'name' => $sender->getName(),
                        'balance' => $sender->getBalance()
                    ],
                    'recipient' => [
                        'id' => $recipient->getId()->toString(),
                        'name' => $recipient->getName(),
                        'balance' => $recipient->getBalance()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Depositar dinheiro na conta
     */
    public function deposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            $transaction = $this->transferService->deposit($user, $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Depósito realizado com sucesso',
                'data' => [
                    'transaction_id' => $transaction->getId()->toString(),
                    'amount' => $transaction->getAmount(),
                    'new_balance' => $user->getBalance()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sacar dinheiro da conta
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            $transaction = $this->transferService->withdraw($user, $request->amount);

            return response()->json([
                'success' => true,
                'message' => 'Saque realizado com sucesso',
                'data' => [
                    'transaction_id' => $transaction->getId()->toString(),
                    'amount' => $transaction->getAmount(),
                    'new_balance' => $user->getBalance()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Estornar uma transação (apenas para quem recebeu)
     */
    public function refund(Request $request, string $transactionId): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Buscar a transação
            $transaction = $this->entityManager->find(Transaction::class, $transactionId);
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transação não encontrada'
                ], 404);
            }

            // Verificar se o usuário é o destinatário da transação
            $payeeId = $transaction->getPayee() ? $transaction->getPayee()->getId()->toString() : null;
            if ($payeeId !== $user->getId()->toString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas quem recebeu a transferência pode estorná-la'
                ], 403);
            }

            // Verificar se a transação já foi estornada
            if ($transaction->getStatus() === Transaction::STATUS_REFUNDED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta transação já foi estornada'
                ], 400);
            }

            // Realizar o estorno
            $refundTransaction = $this->transferService->refund($transaction);

            return response()->json([
                'success' => true,
                'message' => 'Estorno realizado com sucesso',
                'data' => [
                    'refund_transaction_id' => $refundTransaction->getId()->toString(),
                    'original_transaction_id' => $transaction->getId()->toString(),
                    'amount' => $refundTransaction->getAmount()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
