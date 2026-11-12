<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ConnectedResourceLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConnectedResourceLinkRepository::class)]
#[ORM\Table(name: 'connected_resource_link')]
#[ORM\Index(name: 'idx_crl_experiment', columns: ['experiment_id'])]
#[ORM\Index(name: 'idx_crl_provider_external', columns: ['provider', 'external_id'])]
#[ORM\Index(name: 'IDX_CRL_ACCOUNT', columns: ['account_id'])]
#[ApiResource(
    shortName: 'ConnectedResourceLink',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['connected_resource_link.read', 'experiment.item']],
    denormalizationContext: ['groups' => ['connected_resource_link.write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'experiment' => 'exact',
    'experiment.id' => 'exact',
    'provider' => 'exact',
    'resourceType' => 'exact',
])]
class ConnectedResourceLink implements AccountAwareInterface
{
    #[ApiProperty(identifier: true)]
    #[Groups(['connected_resource_link.read', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(type: 'string', length: 64)]
    private string $provider;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(name: 'external_id', type: 'string', length: 255)]
    private string $externalId;

    #[Assert\Length(max: 1024)]
    #[Assert\Url(requireTld: false)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $url = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(name: 'resource_type', type: 'string', length: 64)]
    private string $resourceType;

    #[Assert\Length(max: 255)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[Assert\Length(max: 255)]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(name: 'subject_external_id', type: 'string', length: 255, nullable: true)]
    private ?string $subjectExternalId = null;

    #[Groups(['connected_resource_link.read', 'connected_resource_link.write', 'experiment.read', 'experiment.item'])]
    #[ORM\Column(name: 'synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[Assert\NotNull]
    #[Groups(['connected_resource_link.read', 'connected_resource_link.write'])]
    #[ORM\ManyToOne(targetEntity: Experiment::class, inversedBy: 'connectedResourceLinks')]
    #[ORM\JoinColumn(name: 'experiment_id', nullable: false, onDelete: 'CASCADE')]
    private ?Experiment $experiment = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false, writable: false)]
    private Account $account;

    #[Groups(['connected_resource_link.read'])]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[Groups(['connected_resource_link.read'])]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
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

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getSubjectExternalId(): ?string
    {
        return $this->subjectExternalId;
    }

    public function setSubjectExternalId(?string $subjectExternalId): self
    {
        $this->subjectExternalId = $subjectExternalId;

        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeImmutable $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getExperiment(): ?Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(?Experiment $experiment): self
    {
        $this->experiment = $experiment;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
