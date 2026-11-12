<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\CanonicalFormStatus;
use App\Repository\CanonicalFormRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A source-agnostic canonical form: the internal, versioned target model a
 * crosswalk maps an ELN template onto. Holds sections and fields.
 */
#[ORM\Entity(repositoryClass: CanonicalFormRepository::class)]
#[ORM\Table(name: 'crosswalk_canonical_form')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/canonical-forms',
        ),
        new Post(
            uriTemplate: '/canonical-forms',
        ),
        new Get(
            uriTemplate: '/canonical-forms/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['canonical_form.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['canonical_form.write'],
    ],
)]
class CanonicalForm implements AccountAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['canonical_form.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private ?string $domain = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => '1.0.0'])]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private string $version = '1.0.0';

    #[ORM\Column(type: 'string', enumType: CanonicalFormStatus::class)]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private CanonicalFormStatus $status = CanonicalFormStatus::DRAFT;

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['canonical_form.read', 'canonical_form.write'])]
    private array $sourceLinks = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['canonical_form.read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['canonical_form.read'])]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, CanonicalFormSection>
     */
    #[ORM\OneToMany(
        mappedBy: 'canonicalForm',
        targetEntity: CanonicalFormSection::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    #[Groups(['canonical_form.read'])]
    private Collection $sections;

    /**
     * @var Collection<int, CanonicalFormField>
     */
    #[ORM\OneToMany(
        mappedBy: 'canonicalForm',
        targetEntity: CanonicalFormField::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    #[Groups(['canonical_form.read'])]
    private Collection $fields;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->sections = new ArrayCollection();
        $this->fields = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getStatus(): CanonicalFormStatus
    {
        return $this->status;
    }

    public function setStatus(CanonicalFormStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getSourceLinks(): array
    {
        return $this->sourceLinks;
    }

    /**
     * @param array<int|string, mixed> $sourceLinks
     */
    public function setSourceLinks(array $sourceLinks): self
    {
        $this->sourceLinks = $sourceLinks;

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, CanonicalFormSection>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(CanonicalFormSection $section): self
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setCanonicalForm($this);
        }

        return $this;
    }

    public function removeSection(CanonicalFormSection $section): self
    {
        $this->sections->removeElement($section);

        return $this;
    }

    /**
     * @return Collection<int, CanonicalFormField>
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(CanonicalFormField $field): self
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setCanonicalForm($this);
        }

        return $this;
    }

    public function removeField(CanonicalFormField $field): self
    {
        $this->fields->removeElement($field);

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

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
