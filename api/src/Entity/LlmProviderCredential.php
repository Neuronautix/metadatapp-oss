<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LlmProviderCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LlmProviderCredentialRepository::class)]
#[ORM\Table(name: 'llm_provider_credential')]
#[ORM\UniqueConstraint(name: 'uniq_llm_provider_user', columns: ['provider', 'user_id'])]
class LlmProviderCredential implements AccountAwareInterface, UserAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 255)]
    private string $model = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedApiKey = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $apiKeyHint = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getEncryptedApiKey(): ?string
    {
        return $this->encryptedApiKey;
    }

    public function setEncryptedApiKey(?string $encryptedApiKey): self
    {
        $this->encryptedApiKey = $encryptedApiKey;

        return $this;
    }

    public function getApiKeyHint(): ?string
    {
        return $this->apiKeyHint;
    }

    public function setApiKeyHint(?string $apiKeyHint): self
    {
        $this->apiKeyHint = $apiKeyHint;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
