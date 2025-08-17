<?php

namespace Tests\Integration\Services;

use App\Entities\Transaction;
use App\Entities\User;
use App\Services\TransferService;
use App\Services\ExternalServices\AuthorizationService;
use App\Services\ExternalServices\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;
    private EntityManagerInterface $entityManager;
    private User $commonUser;
    private User $merchantUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = app(EntityManagerInterface::class);
        $this->transferService = app(TransferService::class);
        
        // Criar usuários para testes
        $this->createTestUsers();
    }

    private function createTestUsers(): void
    {
        // Usuário comum
        $this->commonUser = new User();
        $this->commonUser->setName('João Silva')
                        ->setEmail('joao@example.com')
                        ->setDocument('12345678901')
                        ->setPassword(bcrypt('password'))
                        ->setType(User::TYPE_COMMON)
                        ->setBalance(1000.00);

        // Usuário lojista
        $this->merchantUser = new User();
        $this->merchantUser->setName('Loja ABC')
                          ->setEmail('loja@example.com')
                          ->setDocument('98765432100')
                          ->setPassword(bcrypt('password'))
                          ->setType(User::TYPE_MERCHANT)
                          ->setBalance(500.00);

        $this->entityManager->persist($this->commonUser);
        $this->entityManager->persist($this->merchantUser);
        $this->entityManager->flush();
    }

    public function testSuccessfulTransferFromCommonToMerchant(): void
    {
        // Arrange
        $amount = 200.00;
        $description = 'Pagamento de compra';
        $initialCommonBalance = $this->commonUser->getBalance();
        $initialMerchantBalance = $this->merchantUser->getBalance();

        // Act
        $transaction = $this->transferService->transfer(
            $this->commonUser,
            $this->merchantUser,
            $amount,
            $description
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(Transaction::TYPE_TRANSFER, $transaction->getType());
        $this->assertEquals($amount, $transaction->getAmount());
        $this->assertEquals($description, $transaction->getDescription());
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->getStatus());

        // Verificar se os saldos foram atualizados
        $this->entityManager->refresh($this->commonUser);
        $this->entityManager->refresh($this->merchantUser);

        $this->assertEquals($initialCommonBalance - $amount, $this->commonUser->getBalance());
        $this->assertEquals($initialMerchantBalance + $amount, $this->merchantUser->getBalance());

        // Verificar se a transação foi persistida no banco
        $this->assertDatabaseHas('transactions', [
            'amount' => $amount,
            'type' => Transaction::TYPE_TRANSFER,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => $description
        ]);
    }

    public function testSuccessfulTransferBetweenCommonUsers(): void
    {
        // Criar segundo usuário comum
        $secondCommonUser = new User();
        $secondCommonUser->setName('Maria Silva')
                        ->setEmail('maria@example.com')
                        ->setDocument('11111111111')
                        ->setPassword(bcrypt('password'))
                        ->setType(User::TYPE_COMMON)
                        ->setBalance(300.00);

        $this->entityManager->persist($secondCommonUser);
        $this->entityManager->flush();

        // Arrange
        $amount = 150.00;
        $initialSenderBalance = $this->commonUser->getBalance();
        $initialRecipientBalance = $secondCommonUser->getBalance();

        // Act
        $transaction = $this->transferService->transfer(
            $this->commonUser,
            $secondCommonUser,
            $amount,
            'Transferência entre amigos'
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->getStatus());

        // Verificar saldos
        $this->entityManager->refresh($this->commonUser);
        $this->entityManager->refresh($secondCommonUser);

        $this->assertEquals($initialSenderBalance - $amount, $this->commonUser->getBalance());
        $this->assertEquals($initialRecipientBalance + $amount, $secondCommonUser->getBalance());
    }

    public function testTransferFailsWithInsufficientBalance(): void
    {
        // Arrange
        $amount = 2000.00; // Valor maior que o saldo disponível

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo insuficiente para realizar a transferência');

        $this->transferService->transfer(
            $this->commonUser,
            $this->merchantUser,
            $amount
        );
    }

    public function testMerchantCannotSendMoney(): void
    {
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Usuário não pode enviar dinheiro');

        $this->transferService->transfer(
            $this->merchantUser,
            $this->commonUser,
            100.00
        );
    }

    public function testTransferRollbackWhenExceptionOccurs(): void
    {
        // Arrange
        $amount = 100.00;
        $initialCommonBalance = $this->commonUser->getBalance();
        $initialMerchantBalance = $this->merchantUser->getBalance();

        // Mock do serviço de autorização para falhar
        $authService = $this->createMock(AuthorizationService::class);
        $authService->method('authorize')->willReturn(false);

        $transferService = new TransferService(
            $this->entityManager,
            $authService,
            app(NotificationService::class)
        );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Transferência não autorizada pelo serviço externo');

        try {
            $transferService->transfer($this->commonUser, $this->merchantUser, $amount);
        } catch (\Exception $e) {
            // Verificar se os saldos não foram alterados (rollback)
            $this->entityManager->refresh($this->commonUser);
            $this->entityManager->refresh($this->merchantUser);

            $this->assertEquals($initialCommonBalance, $this->commonUser->getBalance());
            $this->assertEquals($initialMerchantBalance, $this->merchantUser->getBalance());

            throw $e;
        }
    }

    public function testTransferCreatesCorrectDatabaseRecords(): void
    {
        // Arrange
        $amount = 250.00;
        $description = 'Teste integração';

        // Act
        $transaction = $this->transferService->transfer(
            $this->commonUser,
            $this->merchantUser,
            $amount,
            $description
        );

        // Assert - Verificar se todos os dados estão corretos no banco
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->getId()->toString(),
            'amount' => $amount,
            'type' => Transaction::TYPE_TRANSFER,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => $description,
            'payer_id' => $this->commonUser->getId()->toString(),
            'payee_id' => $this->merchantUser->getId()->toString()
        ]);

        // Verificar se os saldos dos usuários foram atualizados no banco
        $this->assertDatabaseHas('users', [
            'id' => $this->commonUser->getId()->toString(),
            'balance' => 750.00 // 1000 - 250
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->merchantUser->getId()->toString(),
            'balance' => 750.00 // 500 + 250
        ]);
    }
}
