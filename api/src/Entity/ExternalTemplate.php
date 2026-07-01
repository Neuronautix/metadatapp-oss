<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\ExternalTemplateFormat;
use App\Enum\ExternalTemplateSource;
use App\Repository\ExternalTemplateRepository;
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
 * An ELN template imported from an external source (an `.eln` RO-Crate file,
 * the ELN.community library, elabFTW, etc.). Its raw RO-Crate metadata and any
 * other payload are preserved verbatim; extracted fields live in a child store.
 */
#[ORM\Entity(repositoryClass: ExternalTemplateRepository::class)]
#[ORM\Table(name: 'crosswalk_external_template')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/external-templates',
        ),
        new Post(
            uriTemplate: '/external-templates',
        ),
        new Get(
            uriTemplate: '/external-templates/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['external_template.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['external_template.write'],
    ],
)]
class ExternalTemplate implements AccountAwareInterface, UserAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['external_template.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', enumType: ExternalTemplateSource::class)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private ExternalTemplateSource $source = ExternalTemplateSource::ELN_FILE;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Assert\Length(max: 1024)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private ?string $version = null;

    #[ORM\Column(type: 'string', enumType: ExternalTemplateFormat::class)]
    #[Groups(['external_template.read', 'external_template.write'])]
    private ExternalTemplateFormat $format = ExternalTemplateFormat::ELN_RO_CRATE;

    /**
     * The parsed ro-crate-metadata.json graph.
     *
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['external_template.read'])]
    private ?array $roCrateMetadata = null;

    /**
     * Any other raw import payload.
     *
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['external_template.read'])]
    private ?array $rawPayload = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[Groups(['external_template.read'])]
    private ?string $fileHash = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['external_template.read'])]
    private \DateTimeImmutable $importedAt;

    /**
     * @var Collection<int, ExtractedTemplateField>
     */
    #[ORM\OneToMany(
        mappedBy: 'externalTemplate',
        targetEntity: ExtractedTemplateField::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    #[Groups(['external_template.read'])]
    private Collection $extractedFields;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
        $this->extractedFields = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSource(): ExternalTemplateSource
    {
        return $this->source;
    }

    public function setSource(ExternalTemplateSource $source): self
    {
        $this->source = $source;

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

    public function getFormat(): ExternalTemplateFormat
    {
        return $this->format;
    }

    public function setFormat(ExternalTemplateFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getRoCrateMetadata(): ?array
    {
        return $this->roCrateMetadata;
    }

    /**
     * @param array<int|string, mixed>|null $roCrateMetadata
     */
    public function setRoCrateMetadata(?array $roCrateMetadata): self
    {
        $this->roCrateMetadata = $roCrateMetadata;

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

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): self
    {
        $this->fileHash = $fileHash;

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

    /**
     * @return Collection<int, ExtractedTemplateField>
     */
    public function getExtractedFields(): Collection
    {
        return $this->extractedFields;
    }

    public function addExtractedField(ExtractedTemplateField $extractedField): self
    {
        if (!$this->extractedFields->contains($extractedField)) {
            $this->extractedFields->add($extractedField);
            $extractedField->setExternalTemplate($this);
        }

        return $this;
    }

    public function removeExtractedField(ExtractedTemplateField $extractedField): self
    {
        $this->extractedFields->removeElement($extractedField);

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
