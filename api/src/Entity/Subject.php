<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Mcp\State\SubjectListToolProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[McpTool(
    name: 'get_subject',
    description: 'Retrieve details for a specific animal subject (mouse, zebrafish, rat, etc.) by its UUID.',
)]
#[ApiResource(
    description: 'A research subject (animal) with biological attributes (species, sex, birth date, strain, genotype, health status, weight, pregnancy state) and housing.',
    operations: [
        new Get(
            description: 'Retrieve a single subject by UUID with its biological attributes and housing.',
        ),
        new Post(
            description: 'Register a new subject.',
        ),
        new Put(
            description: 'Replace all properties of a subject.',
        ),
        new Patch(
            description: 'Partially update a subject (e.g., weight, health status).',
        ),
        new Delete(
            description: 'Remove a subject.',
        ),
        new GetCollection(
            description: 'List subjects accessible to the authenticated account. Supports filtering by species, name, health status, weight range, birth date, genotype, and cage.',
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['subject.read', 'project.read', 'enum:read', 'read:nested'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
        ),
    ],
    mcp: [
        'list_mice' => new McpTool(
            name: 'list_mice',
            description: 'List and search for mice (Mus musculus subjects). Filter by name, sex, health status, cage, or genotype. Species is automatically set to mouse.',
            uriTemplate: '/subjects',
            provider: SubjectListToolProvider::class,
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['subject.read', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: new Parameters([
                'species' => new QueryParameter(
                    key: 'species',
                    filter: new PartialSearchFilter(),
                    property: 'species',
                    description: 'Filter by species (e.g. mouse, rat, zebrafish). Defaults to mouse for this tool.',
                    schema: ['type' => 'string', 'default' => 'mouse'],
                ),
                'name' => new QueryParameter(
                    key: 'name',
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on subject name or identifier.',
                ),
                'sex' => new QueryParameter(
                    key: 'sex',
                    filter: new PartialSearchFilter(),
                    property: 'sex',
                    description: 'Filter by sex (male or female).',
                ),
                'healthStatus' => new QueryParameter(
                    key: 'healthStatus',
                    filter: new PartialSearchFilter(),
                    property: 'healthStatus',
                    description: 'Filter by health status (e.g. healthy, sick, dead).',
                ),
                'genotype' => new QueryParameter(
                    key: 'genotype',
                    filter: new PartialSearchFilter(),
                    property: 'genotype',
                    description: 'Partial search on genotype string.',
                ),
                'isPregnant' => new QueryParameter(
                    key: 'isPregnant',
                    filter: 'api_platform.doctrine.orm.boolean_filter.instance',
                    property: 'isPregnant',
                    description: 'Filter by pregnancy status (true or false).',
                ),
            ]),
        ),
        'list_zebrafish' => new McpTool(
            name: 'list_zebrafish',
            description: 'List and search for zebrafish subjects organized in tank batches. Filter by name, sex, health status, or genotype. Species is automatically set to zebrafish.',
            uriTemplate: '/subjects',
            provider: SubjectListToolProvider::class,
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['subject.read', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: new Parameters([
                'species' => new QueryParameter(
                    key: 'species',
                    filter: new PartialSearchFilter(),
                    property: 'species',
                    description: 'Filter by species. Defaults to zebrafish for this tool.',
                    schema: ['type' => 'string', 'default' => 'zebrafish'],
                ),
                'name' => new QueryParameter(
                    key: 'name',
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on zebrafish batch name.',
                ),
                'sex' => new QueryParameter(
                    key: 'sex',
                    filter: new PartialSearchFilter(),
                    property: 'sex',
                    description: 'Filter by sex.',
                ),
                'healthStatus' => new QueryParameter(
                    key: 'healthStatus',
                    filter: new PartialSearchFilter(),
                    property: 'healthStatus',
                    description: 'Filter by health status.',
                ),
                'genotype' => new QueryParameter(
                    key: 'genotype',
                    filter: new PartialSearchFilter(),
                    property: 'genotype',
                    description: 'Partial search on genotype / line string.',
                ),
            ]),
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['subject.read', 'project.read', 'enum:read', 'read:nested'],
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial', 'externalId' => 'ipartial', 'species' => 'exact', 'healthStatus' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['birthAt' => DateFilterInterface::EXCLUDE_NULL])]
#[ApiFilter(NumericFilter::class, properties: ['weight'])]
#[ApiFilter(RangeFilter::class, properties: ['weight'])]
#[ApiFilter(BooleanFilter::class, properties: ['isPregnant'])]
class Subject implements AccountAwareInterface, ConnectedAppEntityInterface
{
    #[ApiProperty()]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private ?Uuid $id = null;

    #[ApiProperty(types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private string $name;

    // about name and label
    /*weightMeasurements
     * #[ORM\Column(type: 'string', length: 255)]
     * private string $name; // Technical name (e.g., 'C57BL/6J').
     *
     * #[ORM\Column(type: 'string', length: 255, nullable: true)]
     * private ?string $label = null; // User-facing name (e.g., 'My favorite strain')
     */
    #[ORM\Column(type: 'string', enumType: Species::class)]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private Species $species;

    #[ORM\Column(type: 'string', enumType: Sex::class)]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private Sex $sex;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private \DateTimeInterface $birthAt; // Date of birth (dob)

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Strain::class, cascade: ['persist'])]
    #[Groups(['read:nested'])]
    private Strain $strain;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private ?string $genotype = null;

    #[ORM\ManyToOne(targetEntity: Cage::class, cascade: ['persist'], inversedBy: 'subjects')]
    #[Groups(['read:nested'])]
    private ?Cage $cage = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['subject.read', 'project.read', 'read:nested', 'enum:read'])]
    private ?float $weight = null;

    #[ORM\Column(type: 'string', enumType: HealthStatus::class)]
    #[Groups(['subject.read', 'project.read', 'read:nested', 'enum:read'])]
    private HealthStatus $healthStatus;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private ?bool $isPregnant;

    /**
     * @var Collection<int, Procedure>
     */
    #[Groups(['subject.read', 'subject:read', 'query', 'query_collection', 'procedure:read', 'read:nested'])]
    #[ORM\ManyToMany(targetEntity: Procedure::class, inversedBy: 'subjects')]
    #[ORM\JoinTable(name: 'subject_procedure')]
    #[MaxDepth(1)]
    private Collection $procedures;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['subject.read'])]
    private ?string $externalId = null;

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    private ?string $elaftwExternalId = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    private ?string $fair3rExternalId = null;

    //    #[ApiProperty]
    //    private ?array $externalData = null;
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    #[Groups(['subject.read'])]
    private Account $account;

    /**
     * @var Collection<int, WeightMeasurement>
     */
    #[ORM\OneToMany(WeightMeasurement::class, mappedBy: 'subject', cascade: ['persist'])]
    #[Groups(['subject.read', 'read:nested', 'enum:read'])]
    private Collection $weightMeasurements;

    public function __construct()
    {
        $this->procedures = new ArrayCollection();
        $this->weightMeasurements = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSpecies(): Species
    {
        return $this->species;
    }

    public function setSpecies(Species $species): self
    {
        $this->species = $species;

        return $this;
    }

    public function getSex(): Sex
    {
        return $this->sex;
    }

    public function setSex(Sex $sex): self
    {
        $this->sex = $sex;

        return $this;
    }

    public function getBirthAt(): \DateTimeInterface
    {
        return $this->birthAt;
    }

    public function setBirthAt(\DateTimeInterface $birthAt): self
    {
        $this->birthAt = $birthAt;

        return $this;
    }

    public function getStrain(): Strain
    {
        return $this->strain;
    }

    public function setStrain(Strain $strain): self
    {
        $this->strain = $strain;

        return $this;
    }

    public function getGenotype(): ?string
    {
        return $this->genotype;
    }

    public function setGenotype(?string $genotype): self
    {
        $this->genotype = $genotype;

        return $this;
    }

    public function getCage(): ?Cage
    {
        return $this->cage;
    }

    public function setCage(?Cage $cage): self
    {
        $this->cage = $cage;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getHealthStatus(): HealthStatus
    {
        return $this->healthStatus;
    }

    public function setHealthStatus(HealthStatus $healthStatus): self
    {
        $this->healthStatus = $healthStatus;

        return $this;
    }

    public function getIsPregnant(): ?bool
    {
        return $this->isPregnant;
    }

    public function setIsPregnant(?bool $isPregnant): self
    {
        $this->isPregnant = $isPregnant;

        return $this;
    }

    /**
     * @return Collection<int, Procedure>
     */
    public function getProcedures(): Collection
    {
        return $this->procedures;
    }

    /**
     * @param Collection<int, Procedure> $procedures
     */
    public function setProcedures(Collection $procedures): self
    {
        $this->procedures = $procedures;

        return $this;
    }

    public function addProcedure(Procedure $procedure): void
    {
        if (!$this->procedures->contains($procedure)) {
            $this->procedures[] = $procedure;
        }
    }

    public function removeProcedure(Procedure $procedure): void
    {
        $this->procedures->removeElement($procedure);
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

    //
    //    public function getExternalData(): ?array
    //    {
    //        return $this->externalData;
    //    }
    //
    //    public function setExternalData(?array $externalData): self
    //    {
    //        $this->externalData = $externalData;
    //
    //        return $this;
    //    }
    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @param Collection<int, WeightMeasurement> $weightMeasurements
     */
    public function setWeightMeasurements(Collection $weightMeasurements): self
    {
        $this->weightMeasurements = $weightMeasurements;

        return $this;
    }

    /**
     * @return Collection<int, WeightMeasurement>
     */
    public function getWeightMeasurements(): Collection
    {
        return $this->weightMeasurements;
    }

    public function addWeightMeasurement(WeightMeasurement $weightMeasurement): self
    {
        if (!$this->weightMeasurements->contains($weightMeasurement)) {
            $this->weightMeasurements[] = $weightMeasurement;
            $weightMeasurement->setSubject($this);
        }

        return $this;
    }

    public function removeWeightMeasurement(WeightMeasurement $weightMeasurement): self
    {
        $this->weightMeasurements->removeElement($weightMeasurement);

        return $this;
    }
}
