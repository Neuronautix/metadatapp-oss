<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use App\Enum\EnrichmentType;
use App\Mcp\State\DoctrineListToolProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Uid\Uuid;

/**
 * Represents a Cage (or tank/batch for zebrafish) where subjects are housed.
 */
#[ORM\Entity]
#[McpTool(
    name: 'get_cage',
    description: 'Retrieve details for a specific cage or zebrafish tank by its UUID.',
)]
#[ApiResource(
    description: 'A cage or tank where subjects are housed. Includes housing attributes such as type, format, enrichment, food/water availability, bedding, and animal facility.',
    operations: [
        new Get(
            description: 'Retrieve a single cage/tank by UUID with its housing attributes.',
        ),
        new GetCollection(
            description: 'List cages/tanks accessible to the authenticated account; filterable via MCP tool or REST filters.',
        ),
        new Post(),
        new Put(),
        new Delete(),
    ],
    mcp: [
        'list_cages' => new McpTool(
            name: 'list_cages',
            description: 'List and search for cages or zebrafish tanks/batches. Filter by type, format, or bedding type.',
            uriTemplate: '/cages',
            provider: DoctrineListToolProvider::class,
            normalizationContext: ['groups' => ['read:nested', 'enum:read']],
            parameters: new Parameters([
                'type' => new QueryParameter(
                    key: 'type',
                    filter: new PartialSearchFilter(),
                    property: 'type',
                    description: 'Filter by cage type (e.g. conventional, barrier, zebrafish_tank).',
                ),
                'format' => new QueryParameter(
                    key: 'format',
                    filter: new PartialSearchFilter(),
                    property: 'format',
                    description: 'Filter by cage format.',
                ),
                'beddingType' => new QueryParameter(
                    key: 'beddingType',
                    filter: new PartialSearchFilter(),
                    property: 'beddingType',
                    description: 'Filter by bedding type.',
                ),
            ]),
        ),
    ],
    normalizationContext: ['groups' => ['read:nested', 'enum:read']],
)]
class Cage implements AccountAwareInterface, ConnectedAppEntityInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'], iris: ['https://schema.org/name'])]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[Groups(['read:nested', 'enum:read'])]
    #[ORM\Column(type: 'string', enumType: CageType::class)] // ENUM different cage types, to define in the animal facilities a list to choose from
    private CageType $type;

    #[Groups(['read:nested', 'enum:read'])]
    #[ORM\Column(type: 'string', enumType: CageFormat::class)]
    private CageFormat $format;

    #[Groups(['read:nested', 'enum:read'])]
    #[ORM\Column(type: 'string', nullable: true, enumType: EnrichmentType::class)]
    private ?EnrichmentType $enrichmentType = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hasEnrichments = false;

    /**
     * Water availability.
     * It's a boolean for now, could be a quantity later.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $water = false;

    /**
     * Food availability.
     * It's a boolean for now, could be an enum later, maybe an entity measurable.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'boolean', length: 255)]
    private bool $food = false;

    #[Groups(['read:nested', 'enum:read'])]
    #[ORM\Column(type: 'string', enumType: BeddingType::class)]
    private BeddingType $beddingType;

    #[Groups(['read:nested'])]
    #[ORM\ManyToOne(targetEntity: EnvironmentHousing::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?EnvironmentHousing $environmentHousing = null;

    #[Groups(['read:nested'])]
    #[ORM\ManyToOne(targetEntity: AnimalFacility::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?AnimalFacility $animalFacility = null;

    #[Groups(['read:nested'])]
    #[ORM\ManyToOne(targetEntity: HomeCageMonitoring::class)]
    private ?HomeCageMonitoring $homeCageMonitoring = null;

    /**
     * @var Collection<int, Subject>
     */
    #[Groups(['read:nested'])]
    #[MaxDepth(1)]
    #[ORM\OneToMany(targetEntity: Subject::class, mappedBy: 'cage')]
    private Collection $subjects;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    // externalId
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    private ?string $elaftwExternalId = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    private ?string $fair3rExternalId = null;

    public function __construct()
    {
        $this->subjects = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getType(): CageType
    {
        return $this->type;
    }

    public function setType(CageType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFormat(): CageFormat
    {
        return $this->format;
    }

    public function setFormat(CageFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function hasEnrichments(): bool
    {
        return $this->hasEnrichments;
    }

    public function setHasEnrichments(bool $hasEnrichments): self
    {
        $this->hasEnrichments = $hasEnrichments;

        return $this;
    }

    public function isWater(): bool
    {
        return $this->water;
    }

    public function setWater(bool $water): self
    {
        $this->water = $water;

        return $this;
    }

    public function isFood(): bool
    {
        return $this->food;
    }

    public function setFood(bool $food): self
    {
        $this->food = $food;

        return $this;
    }

    public function getBeddingType(): BeddingType
    {
        return $this->beddingType;
    }

    public function setBeddingType(BeddingType $beddingType): self
    {
        $this->beddingType = $beddingType;

        return $this;
    }

    public function getEnvironmentHousing(): ?EnvironmentHousing
    {
        return $this->environmentHousing;
    }

    public function setEnvironmentHousing(?EnvironmentHousing $environmentHousing): self
    {
        $this->environmentHousing = $environmentHousing;

        return $this;
    }

    public function getAnimalFacility(): ?AnimalFacility
    {
        return $this->animalFacility;
    }

    public function setAnimalFacility(?AnimalFacility $animalFacility): self
    {
        $this->animalFacility = $animalFacility;

        return $this;
    }

    public function getHomeCageMonitoring(): ?HomeCageMonitoring
    {
        return $this->homeCageMonitoring;
    }

    public function setHomeCageMonitoring(?HomeCageMonitoring $homeCageMonitoring): self
    {
        $this->homeCageMonitoring = $homeCageMonitoring;

        return $this;
    }

    public function getEnrichmentType(): ?EnrichmentType
    {
        return $this->enrichmentType;
    }

    public function setEnrichmentType(?EnrichmentType $enrichmentType): self
    {
        $this->enrichmentType = $enrichmentType;

        return $this;
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getSubjects(): Collection
    {
        return $this->subjects;
    }

    /**
     * @param Collection<int, Subject> $subjects
     */
    public function setSubjects(Collection $subjects): self
    {
        $this->subjects = $subjects;

        return $this;
    }

    public function addSubject(Subject $subject): void
    {
        if (!$this->subjects->contains($subject)) {
            $this->subjects->add($subject);
            $subject->setCage($this);
        }
    }

    public function removeSubject(Subject $subject): void
    {
        // set the owning side to null (unless already changed)
        if ($this->subjects->removeElement($subject) && $subject->getCage() === $this) {
            $subject->setCage(null);
        }
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

    public function getElaftwExternalId(): ?string
    {
        return $this->elaftwExternalId;
    }

    public function setElaftwExternalId(?string $elaftwExternalId): self
    {
        $this->elaftwExternalId = $elaftwExternalId;

        return $this;
    }

    public function getFair3rExternalId(): ?string
    {
        return $this->fair3rExternalId;
    }

    public function setFair3rExternalId(?string $fair3rExternalId): self
    {
        $this->fair3rExternalId = $fair3rExternalId;

        return $this;
    }

    #[ApiProperty(types: ['https://schema.org/name'])]
    public function getName(): string
    {
        return 'Cage ' . $this->getId() . ' ' . $this->getType()->value;
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
