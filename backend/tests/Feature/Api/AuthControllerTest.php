<?php

namespace Tests\Feature\Api;

use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = app(EntityManagerInterface::class);
    }

    public function testUserRegistrationSuccessful(): void
    {
        $userData = [
            'name' => 'João Silva',
            'email' => 'joao.teste@example.com',
            'document' => '12345678901',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'common',
            'balance' => 1000.00
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Usuário criado com sucesso'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'user' => [
                             'id',
                             'name',
                             'email',
                             'document',
                             'type',
                             'balance'
                         ],
                         'token'
                     ]
                 ]);

        // Verificar se usuário foi criado no banco
        $this->assertDatabaseHas('users', [
            'email' => 'joao.teste@example.com',
            'document' => '12345678901'
        ]);
    }

    public function testUserRegistrationWithDuplicateEmail(): void
    {
        // Criar usuário inicial
        $user = new User();
        $user->setName('João Silva')
             ->setEmail('joao@example.com')
             ->setDocument('12345678901')
             ->setPassword(bcrypt('password'))
             ->setType(User::TYPE_COMMON)
             ->setBalance(1000.00);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Tentar criar usuário com mesmo email
        $userData = [
            'name' => 'Maria Silva',
            'email' => 'joao@example.com', // Email duplicado
            'document' => '98765432100',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'common'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Este email já está sendo utilizado'
                 ]);
    }

    public function testUserRegistrationWithDuplicateDocument(): void
    {
        // Criar usuário inicial
        $user = new User();
        $user->setName('João Silva')
             ->setEmail('joao@example.com')
             ->setDocument('12345678901')
             ->setPassword(bcrypt('password'))
             ->setType(User::TYPE_COMMON)
             ->setBalance(1000.00);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Tentar criar usuário com mesmo documento
        $userData = [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'document' => '12345678901', // Documento duplicado
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'common'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Este CPF/CNPJ já está sendo utilizado'
                 ]);
    }

    public function testUserLoginSuccessful(): void
    {
        // Criar usuário para login
        $user = new User();
        $user->setName('João Silva')
             ->setEmail('joao@example.com')
             ->setDocument('12345678901')
             ->setPassword(bcrypt('password123'))
             ->setType(User::TYPE_COMMON)
             ->setBalance(1000.00);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $loginData = [
            'email' => 'joao@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Login realizado com sucesso'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         'user' => [
                             'id',
                             'name',
                             'email',
                             'document',
                             'type',
                             'balance'
                         ],
                         'token'
                     ]
                 ]);
    }

    public function testUserLoginWithInvalidCredentials(): void
    {
        $loginData = [
            'email' => 'naoexiste@example.com',
            'password' => 'senhaerrada'
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Credenciais inválidas'
                 ]);
    }

    public function testValidationErrors(): void
    {
        $userData = [
            'name' => '', // Nome vazio
            'email' => 'email-invalido', // Email inválido
            'document' => '123', // Documento muito curto
            'password' => '123', // Senha muito curta
            'type' => 'tipo-invalido' // Tipo inválido
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Dados de entrada inválidos'
                 ])
                 ->assertJsonStructure([
                     'errors' => [
                         'name',
                         'email',
                         'password',
                         'type'
                     ]
                 ]);
    }
}
