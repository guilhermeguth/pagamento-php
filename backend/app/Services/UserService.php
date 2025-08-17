<?php

namespace App\Services;

use App\Entities\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;

class UserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function createUser(array $data): User
    {
        $this->validateUserData($data);

        // Verificar se já existe usuário com mesmo email ou documento
        if ($this->userRepository->existsByEmail($data['email'])) {
            throw new \Exception('E-mail já cadastrado no sistema');
        }

        if ($this->userRepository->existsByDocument($data['document'])) {
            throw new \Exception('CPF/CNPJ já cadastrado no sistema');
        }

        $user = new User();
        $user->setName($data['name'])
            ->setDocument($this->cleanDocument($data['document']))
            ->setEmail($data['email'])
            ->setPassword($data['password'])
            ->setType($data['type']);

        // Se foi informado um saldo inicial, adicionar
        if (isset($data['initial_balance']) && $data['initial_balance'] > 0) {
            $user->setBalance($data['initial_balance']);
        }

        $savedUser = $this->userRepository->save($user);

        Log::info('User created successfully', [
            'user_id' => $savedUser->getId()->toString(),
            'email' => $savedUser->getEmail(),
            'type' => $savedUser->getType()
        ]);

        return $savedUser;
    }

    public function updateUser(User $user, array $data): User
    {
        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            if ($this->userRepository->existsByEmail($data['email'])) {
                throw new \Exception('E-mail já cadastrado no sistema');
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['password'])) {
            $user->setPassword($data['password']);
        }

        $updatedUser = $this->userRepository->save($user);

        Log::info('User updated successfully', [
            'user_id' => $updatedUser->getId()->toString()
        ]);

        return $updatedUser;
    }

    public function getUserBalance(User $user): array
    {
        return [
            'user_id' => $user->getId()->toString(),
            'name' => $user->getName(),
            'balance' => $user->getBalance(),
            'type' => $user->getType()
        ];
    }

    public function listUsers(?string $type = null): array
    {
        if ($type === User::TYPE_COMMON) {
            return $this->userRepository->findCommonUsers();
        }

        if ($type === User::TYPE_MERCHANT) {
            return $this->userRepository->findMerchants();
        }

        return $this->userRepository->findAll();
    }

    private function validateUserData(array $data): void
    {
        $requiredFields = ['name', 'document', 'email', 'password', 'type'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("Campo '$field' é obrigatório");
            }
        }

        if (!in_array($data['type'], [User::TYPE_COMMON, User::TYPE_MERCHANT])) {
            throw new \Exception('Tipo de usuário inválido');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('E-mail inválido');
        }

        if (!$this->isValidDocument($data['document'])) {
            throw new \Exception('CPF/CNPJ inválido');
        }

        if (strlen($data['password']) < 6) {
            throw new \Exception('Senha deve ter pelo menos 6 caracteres');
        }
    }

    private function cleanDocument(string $document): string
    {
        return preg_replace('/[^0-9]/', '', $document);
    }

    private function isValidDocument(string $document): bool
    {
        $cleanDocument = $this->cleanDocument($document);

        // Validar CPF (11 dígitos)
        if (strlen($cleanDocument) === 11) {
            return $this->isValidCPF($cleanDocument);
        }

        // Validar CNPJ (14 dígitos)
        if (strlen($cleanDocument) === 14) {
            return $this->isValidCNPJ($cleanDocument);
        }

        return false;
    }

    private function isValidCPF(string $cpf): bool
    {
        // Eliminar CPFs conhecidos como inválidos
        if (in_array($cpf, [
            '00000000000', '11111111111', '22222222222', '33333333333',
            '44444444444', '55555555555', '66666666666', '77777777777',
            '88888888888', '99999999999'
        ])) {
            return false;
        }

        // Validar primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        if (intval($cpf[9]) !== $digit1) {
            return false;
        }

        // Validar segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return intval($cpf[10]) === $digit2;
    }

    private function isValidCNPJ(string $cnpj): bool
    {
        // Eliminar CNPJs conhecidos como inválidos
        if (in_array($cnpj, [
            '00000000000000', '11111111111111', '22222222222222',
            '33333333333333', '44444444444444', '55555555555555',
            '66666666666666', '77777777777777', '88888888888888',
            '99999999999999'
        ])) {
            return false;
        }

        // Validar primeiro dígito verificador
        $sum = 0;
        $weight = 5;
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($cnpj[$i]) * $weight;
            $weight = ($weight === 2) ? 9 : $weight - 1;
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        if (intval($cnpj[12]) !== $digit1) {
            return false;
        }

        // Validar segundo dígito verificador
        $sum = 0;
        $weight = 6;
        for ($i = 0; $i < 13; $i++) {
            $sum += intval($cnpj[$i]) * $weight;
            $weight = ($weight === 2) ? 9 : $weight - 1;
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        return intval($cnpj[13]) === $digit2;
    }
}
