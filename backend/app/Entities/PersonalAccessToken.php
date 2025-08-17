<?php

namespace App\Entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Laravel\Sanctum\Contracts\HasAbilities;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'personal_access_tokens')]
#[ORM\HasLifecycleCallbacks]
class PersonalAccessToken implements HasAbilities
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $tokenable_id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $tokenable_type;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $abilities = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $last_used_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expires_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $created_at;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $updated_at;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'tokenable_id', referencedColumnName: 'id')]
    private User $tokenable;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getTokenableId(): UuidInterface
    {
        return $this->tokenable_id;
    }

    public function setTokenableId(UuidInterface $tokenable_id): self
    {
        $this->tokenable_id = $tokenable_id;
        return $this;
    }

    public function getTokenableType(): string
    {
        return $this->tokenable_type;
    }

    public function setTokenableType(string $tokenable_type): self
    {
        $this->tokenable_type = $tokenable_type;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = hash('sha256', $token);
        return $this;
    }

    public function getAbilities(): array
    {
        return $this->abilities ? json_decode($this->abilities, true) : ['*'];
    }

    public function setAbilities(?array $abilities): self
    {
        $this->abilities = $abilities ? json_encode($abilities) : null;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTime
    {
        return $this->last_used_at;
    }

    public function setLastUsedAt(?\DateTime $last_used_at): self
    {
        $this->last_used_at = $last_used_at;
        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expires_at;
    }

    public function setExpiresAt(?\DateTime $expires_at): self
    {
        $this->expires_at = $expires_at;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function getTokenable(): User
    {
        return $this->tokenable;
    }

    public function setTokenable(User $tokenable): self
    {
        $this->tokenable = $tokenable;
        $this->tokenable_id = $tokenable->getId();
        $this->tokenable_type = User::class;
        return $this;
    }

    // Implementação da interface HasAbilities
    public function can($ability): bool
    {
        $abilities = $this->getAbilities();
        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    public function cant($ability): bool
    {
        return !$this->can($ability);
    }

    public function tokenCan(string $ability): bool
    {
        return $this->can($ability);
    }

    public function tokenCant(string $ability): bool
    {
        return $this->cant($ability);
    }

    public function findToken(string $token): ?self
    {
        return hash('sha256', $token) === $this->token ? $this : null;
    }
}
