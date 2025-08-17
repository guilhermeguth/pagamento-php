<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class AuthController extends Controller
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'document' => 'required|string|max:20',
            'type' => 'required|in:common,merchant',
            'balance' => 'nullable|numeric|min:0|max:999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar se email já existe
        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $request->email]);

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Este email já está sendo utilizado'
            ], 422);
        }

        // Verificar se CPF/CNPJ já existe
        $existingCpfCnpj = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['document' => $request->document]);

        if ($existingCpfCnpj) {
            return response()->json([
                'success' => false,
                'message' => 'Este CPF/CNPJ já está sendo utilizado'
            ], 422);
        }

        try {
            $user = new User();
            $user->setName($request->name);
            $user->setEmail($request->email);
            $user->setPassword(bcrypt($request->password));
            $user->setDocument($request->document);
            $user->setType($request->type === 'merchant' ? User::TYPE_MERCHANT : User::TYPE_COMMON);
            $user->setBalance($request->balance ?? 0);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Criar token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'data' => [
                    'user' => [
                        'id' => $user->getId()->toString(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail(),
                        'document' => $user->getDocument(),
                        'type' => $user->getType(),
                        'balance' => $user->getBalance()
                    ],
                    'token' => $token
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro desconhecido'
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $request->email]);

            if (!$user || !password_verify($request->password, $user->getPassword())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas'
                ], 401);
            }

            // Criar token
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'user' => [
                        'id' => $user->getId()->toString(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail(),
                        'document' => $user->getDocument(),
                        'type' => $user->getType(),
                        'balance' => $user->getBalance()
                    ],
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro desconhecido'
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->deleteCurrentAccessToken();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro desconhecido'
            ], 500);
        }
    }

    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->getId()->toString(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'document' => $user->getDocument(),
                    'type' => $user->getType(),
                    'balance' => $user->getBalance()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro desconhecido'
            ], 500);
        }
    }
}
