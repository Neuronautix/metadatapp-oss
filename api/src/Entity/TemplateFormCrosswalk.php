<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;
use App\Repository\TemplateFormCrosswalkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;

/**
 * The join between an imported ELN template and a canonical form (optionally an
 * external CDE Form): the crosswalk aggregate root. Holds field-level mappings.
 */
#[ORM\Entity(repositoryClass: TemplateFormCrosswalkRepository::class)]
#[ORM\Table(name: 'crosswalk_template_form_crosswalk')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/template-crosswalks',
        ),
        new Post(
            uriTemplate: '/template-crosswalks',
        ),
        new Get(
            uriTemplate: '/template-crosswalks/{id}',
        ),
        new Patch(
            uriTemplate: '/template-crosswalks/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['template_form_crosswalk.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['template_form_crosswalk.write'],
    ],
)]
class TemplateFormCrosswalk implements AccountAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['template_form_crosswalk.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ExternalTemplate::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private ExternalTemplate $externalTemplate;

    #[ORM\ManyToOne(targetEntity: ExternalForm::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private ?ExternalForm $externalForm = null;

    #[ORM\ManyToOne(targetEntity: CanonicalForm::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private CanonicalForm $canonicalForm;

    #[ORM\Column(type: 'string', enumType: CrosswalkMappingType::class)]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private CrosswalkMappingType $mappingType = CrosswalkMappingType::RELATED;

    #[ORM\Column(type: 'string', enumType: MappingStatus::class)]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private MappingStatus $mappingStatus = MappingStatus::SUGGESTED;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private ?float $confidence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private ?string $evidence = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['template_form_crosswalk.read'])]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['template_form_crosswalk.read'])]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => '1.0.0'])]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private string $version = '1.0.0';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['template_form_crosswalk.read', 'template_form_crosswalk.write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['template_form_crosswalk.read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, FieldCrosswalk>
     */
    #[ORM\OneToMany(
        mappedBy: 'templateFormCrosswalk',
        targetEntity: FieldCrosswalk::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Groups(['template_form_crosswalk.read'])]
    private Collection $fieldCrosswalks;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->fieldCrosswalks = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getExternalTemplate(): ExternalTemplate
    {
        return $this->externalTemplate;
    }

    public function setExternalTemplate(ExternalTemplate $externalTemplate): self
    {
        $this->externalTemplate = $externalTemplate;

        return $this;
    }

    public function getExternalForm(): ?ExternalForm
    {
        return $this->externalForm;
    }

    public function setExternalForm(?ExternalForm $externalForm): self
    {
        $this->externalForm = $externalForm;

        return $this;
    }

    public function getCanonicalForm(): CanonicalForm
    {
        return $this->canonicalForm;
    }

    public function setCanonicalForm(CanonicalForm $canonicalForm): self
    {
        $this->canonicalForm = $canonicalForm;

        return $this;
    }

    public function getMappingType(): CrosswalkMappingType
    {
        return $this->mappingType;
    }

    public function setMappingType(CrosswalkMappingType $mappingType): self
    {
        $this->mappingType = $mappingType;

        return $this;
    }

    public function getMappingStatus(): MappingStatus
    {
        return $this->mappingStatus;
    }

    public function setMappingStatus(MappingStatus $mappingStatus): self
    {
        $this->mappingStatus = $mappingStatus;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getEvidence(): ?string
    {
        return $this->evidence;
    }

    public function setEvidence(?string $evidence): self
    {
        $this->evidence = $evidence;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    /**
     * @return Collection<int, FieldCrosswalk>
     */
    public function getFieldCrosswalks(): Collection
    {
        return $this->fieldCrosswalks;
    }

    public function addFieldCrosswalk(FieldCrosswalk $fieldCrosswalk): self
    {
        if (!$this->fieldCrosswalks->contains($fieldCrosswalk)) {
            $this->fieldCrosswalks->add($fieldCrosswalk);
            $fieldCrosswalk->setTemplateFormCrosswalk($this);
        }

        return $this;
    }

    public function removeFieldCrosswalk(FieldCrosswalk $fieldCrosswalk): self
    {
        $this->fieldCrosswalks->removeElement($fieldCrosswalk);

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
}
