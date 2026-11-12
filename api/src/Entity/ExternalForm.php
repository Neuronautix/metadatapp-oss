<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\ExternalFormSource;
use App\Repository\ExternalFormRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An external CDE Form (e.g. an NIH CDE form) imported as a reference target
 * for crosswalks. Raw payload and provenance are preserved verbatim.
 */
#[ORM\Entity(repositoryClass: ExternalFormRepository::class)]
#[ORM\Table(name: 'crosswalk_external_form')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/external-forms',
        ),
        new Post(
            uriTemplate: '/external-forms',
        ),
        new Get(
            uriTemplate: '/external-forms/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['external_form.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['external_form.write'],
    ],
)]
class ExternalForm implements AccountAwareInterface, UserAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['external_form.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', enumType: ExternalFormSource::class)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private ExternalFormSource $source = ExternalFormSource::METADATAPP;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Assert\Length(max: 1024)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['external_form.read', 'external_form.write'])]
    private ?string $version = null;

    /**
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['external_form.read'])]
    private ?array $rawPayload = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['external_form.read'])]
    private \DateTimeImmutable $importedAt;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSource(): ExternalFormSource
    {
        return $this->source;
    }

    public function setSource(ExternalFormSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    /**
     * @param array<int|string, mixed>|null $rawPayload
     */
    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;

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
}
