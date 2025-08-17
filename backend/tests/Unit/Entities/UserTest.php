<?php

namespace Tests\Unit\Entities;

use App\Entities\User;
use App\Traits\HasApiTokens;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User();
        $this->user->setName('João Silva')
                  ->setEmail('joao@example.com')
                  ->setDocument('12345678901')
                  ->setPassword('password123')
                  ->setType(User::TYPE_COMMON)
                  ->setBalance(1000.00);
    }

    public function testUserCreation(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
        $this->assertEquals('João Silva', $this->user->getName());
        $this->assertEquals('joao@example.com', $this->user->getEmail());
        $this->assertEquals('12345678901', $this->user->getDocument());
        $this->assertEquals(User::TYPE_COMMON, $this->user->getType());
        $this->assertEquals(1000.00, $this->user->getBalance());
    }

    public function testCommonUserCanSendMoney(): void
    {
        $this->user->setType(User::TYPE_COMMON);
        $this->assertTrue($this->user->canSendMoney());
    }

    public function testMerchantUserCannotSendMoney(): void
    {
        $this->user->setType(User::TYPE_MERCHANT);
        $this->assertFalse($this->user->canSendMoney());
    }

    public function testHasSufficientBalanceWithSufficientAmount(): void
    {
        $this->user->setBalance(1000.00);
        $this->assertTrue($this->user->hasSufficientBalance(500.00));
    }

    public function testHasSufficientBalanceWithInsufficientAmount(): void
    {
        $this->user->setBalance(100.00);
        $this->assertFalse($this->user->hasSufficientBalance(500.00));
    }

    public function testHasSufficientBalanceWithExactAmount(): void
    {
        $this->user->setBalance(500.00);
        $this->assertTrue($this->user->hasSufficientBalance(500.00));
    }

    public function testGetAuthIdentifierName(): void
    {
        $this->assertEquals('id', $this->user->getAuthIdentifierName());
    }

    public function testGetAuthIdentifier(): void
    {
        $id = Uuid::uuid4();
        $reflection = new \ReflectionClass($this->user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->user, $id);

        $this->assertEquals($id->toString(), $this->user->getAuthIdentifier());
    }

    public function testGetAuthPassword(): void
    {
        $password = 'hashed_password';
        $this->user->setPassword($password);
        $this->assertEquals($password, $this->user->getAuthPassword());
    }

    public function testGetRememberToken(): void
    {
        $this->assertNull($this->user->getRememberToken());
    }

    public function testSetRememberToken(): void
    {
        $token = 'remember_token';
        $this->user->setRememberToken($token);
        $this->assertEquals($token, $this->user->getRememberToken());
    }

    public function testGetRememberTokenName(): void
    {
        $this->assertEquals('remember_token', $this->user->getRememberTokenName());
    }

    public function testUserImplementsAuthenticatable(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Auth\Authenticatable::class, $this->user);
    }

    public function testUserUsesHasApiTokensTrait(): void
    {
        $traits = class_uses($this->user);
        $this->assertContains(HasApiTokens::class, $traits);
    }
}
