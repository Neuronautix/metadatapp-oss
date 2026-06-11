<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents an Institution.
 */
#[ORM\Entity]
// #[ApiResource(
//    types: 'https://schema.org/Organization',
//    normalizationContext: [
//        AbstractNormalizer::GROUPS => ['account.read'],
//    ],
//    denormalizationContext: [
//        AbstractNormalizer::GROUPS => ['account.write'],
//    ]
// )]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Post(security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[UniqueEntity('externalKey', ignoreNull: true)]
class Account
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['account.read'])]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(types: 'https://schema.org/name', iris: ['https://schema.org/name'])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['account.read', 'account.write'])]
    private string $name;

    #[ApiProperty(types: 'https://schema.org/identifier')]
    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    #[Groups(['account.read', 'account.write'])]
    private ?string $externalKey = null;

    /**
     * The feature-flag preset applied to all users of this account (e.g. "zefix", "tecniplast", "god").
     * When set, the /api/feature-flags/overrides endpoint returns the corresponding flag overrides
     * so the frontend automatically activates the right profile on login.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['account.read', 'account.write'])]
    private ?string $featurePreset = null;

    /**
     * The LLM provider configured for this account (e.g. "openai", "anthropic", "gemini").
     * When set together with llmApiKey the AI assistant uses this provider for all users in the account.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $llmProvider = null;

    /**
     * The API key for the configured LLM provider.
     * Never returned in full through the API — only a masked preview is exposed.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $llmApiKey = null;

    /**
     * Optional override for the LLM model name (e.g. "gpt-4o", "claude-3-5-haiku-20241022").
     * When null, the gateway falls back to a sensible default for the chosen provider.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $llmModel = null;

    /**
     * @var Collection<int, User>
     */
    #[ApiProperty(types: 'https://schema.org/person')]
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'account')]
    #[MaxDepth(1)]
    private Collection $users;

    /**
     * @var Collection<int, ConnectedApp>
     */
    #[ORM\OneToMany(targetEntity: ConnectedApp::class, mappedBy: 'account')]
    private Collection $connectedApps;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): void
    {
        $this->id = $id;
    }

    /**
     * @param Collection<int, User> $users
     */
    public function setUsers(Collection $users): void
    {
        $this->users = $users;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): void
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setAccount($this);
        }
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);

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

    public function getExternalKey(): ?string
    {
        return $this->externalKey;
    }

    public function setExternalKey(?string $externalKey): self
    {
        if (null === $externalKey) {
            $this->externalKey = null;

            return $this;
        }

        $trimmedExternalKey = trim($externalKey);
        $this->externalKey = '' !== $trimmedExternalKey ? $trimmedExternalKey : null;

        return $this;
    }

    /**
     * @return Collection<int, ConnectedApp>
     */
    public function getConnectedApps(): Collection
    {
        return $this->connectedApps;
    }

    /**
     * @param Collection<int, ConnectedApp> $connectedApps
     */
    public function setConnectedApps(Collection $connectedApps): self
    {
        $this->connectedApps = $connectedApps;

        return $this;
    }

    public function addConnectedApp(ConnectedApp $connectedApp): self
    {
        if (!$this->connectedApps->contains($connectedApp)) {
            $this->connectedApps->add($connectedApp);
            $connectedApp->setAccount($this);
        }

        return $this;
    }

    public function removeConnectedApp(ConnectedApp $connectedApp): self
    {
        $this->connectedApps->removeElement($connectedApp);

        return $this;
    }

    public function getFeaturePreset(): ?string
    {
        return $this->featurePreset;
    }

    public function setFeaturePreset(?string $featurePreset): self
    {
        $this->featurePreset = $featurePreset;

        return $this;
    }

    public function getLlmProvider(): ?string
    {
        return $this->llmProvider;
    }

    public function setLlmProvider(?string $llmProvider): self
    {
        $this->llmProvider = $llmProvider;

        return $this;
    }

    public function getLlmApiKey(): ?string
    {
        return $this->llmApiKey;
    }

    public function setLlmApiKey(?string $llmApiKey): self
    {
        $this->llmApiKey = $llmApiKey;

        return $this;
    }

    public function getLlmModel(): ?string
    {
        return $this->llmModel;
    }

    public function setLlmModel(?string $llmModel): self
    {
        $this->llmModel = $llmModel;

        return $this;
    }

    /**
     * Returns a masked preview of the API key (e.g. "sk-ab••••••••••••1234").
     */
    public function getLlmApiKeyPreview(): ?string
    {
        $key = $this->llmApiKey;
        if (null === $key || '' === $key) {
            return null;
        }

        $len = \strlen($key);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($key, 0, 4) . str_repeat('•', max(0, $len - 8)) . substr($key, -4);
    }
}
