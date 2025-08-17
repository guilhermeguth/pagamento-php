<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class UserController extends Controller
{
    private UserService $userService;
    private UserRepository $userRepository;

    public function __construct(UserService $userService, UserRepository $userRepository)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type');
            $users = $this->userService->listUsers($type);

            $usersData = array_map(function ($user) {
                return [
                    'id' => $user->getId()->toString(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'document' => $user->getDocument(),
                    'type' => $user->getType(),
                    'balance' => $user->getBalance(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $users);

            return response()->json([
                'success' => true,
                'data' => $usersData
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing users', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar usuários',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'document' => 'required|string|max:20',
                'password' => 'required|string|min:6',
                'type' => 'required|in:common,merchant',
                'initial_balance' => 'nullable|numeric|min:0'
            ]);

            $user = $this->userService->createUser($data);

            return response()->json([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'data' => [
                    'id' => $user->getId()->toString(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'document' => $user->getDocument(),
                    'type' => $user->getType(),
                    'balance' => $user->getBalance(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar usuário',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(Uuid::fromString($id));

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->getId()->toString(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'document' => $user->getDocument(),
                    'type' => $user->getType(),
                    'balance' => $user->getBalance(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuário',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(Uuid::fromString($id));

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email',
                'password' => 'sometimes|required|string|min:6'
            ]);

            $updatedUser = $this->userService->updateUser($user, $data);

            return response()->json([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso',
                'data' => [
                    'id' => $updatedUser->getId()->toString(),
                    'name' => $updatedUser->getName(),
                    'email' => $updatedUser->getEmail(),
                    'document' => $updatedUser->getDocument(),
                    'type' => $updatedUser->getType(),
                    'balance' => $updatedUser->getBalance(),
                    'updated_at' => $updatedUser->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar usuário',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function balance(string $id): JsonResponse
    {
        try {
            $user = $this->userRepository->findById(Uuid::fromString($id));

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            $balanceData = $this->userService->getUserBalance($user);

            return response()->json([
                'success' => true,
                'data' => $balanceData
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user balance', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar saldo do usuário',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar usuário por email
     */
    public function findByEmail(Request $request): JsonResponse
    {
        try {
            $email = $request->query('email');
            
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email é obrigatório'
                ], 400);
            }

            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->getId()->toString(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'type' => $user->getType()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error finding user by email', ['email' => $email, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuário',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
