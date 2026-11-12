<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\InvasivenessType;
use App\Enum\ProcedureType;
use App\Enum\TreatmentCategory;
use App\Enum\TreatmentRoute;
use App\Enum\TreatmentType;
use App\Repository\ProcedureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WIP, the graphql resolver type is not working yet.
 * WE SHOULD NOT HAVE SPECIFIC TYPE PROPERTY in this class
 * we have to handle create abd update.
 */

/**
 * Represents a Procedure in an Experiment.
 */
#[ORM\Entity(repositoryClass: ProcedureRepository::class)]
#[ApiResource(
    description: 'An ISA-Tab Assay / Procedure performed within a Study. Subtypes include Surgery, Treatment, and Behavior.',
    types: 'https://schema.org/MedicalProcedure',
    operations: [
        new Get(
            uriTemplate: '/assay/{id}',
            normalizationContext: [
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ]),
        new GetCollection(
            uriTemplate: '/assay',
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['procedure:list'],
            ]
        ),
        new GetCollection(
            name: 'study_assays',
            uriTemplate: '/study/{id}/assay',
            uriVariables: [
                'id' => new Link(
                    fromClass: Experiment::class,
                    fromProperty: 'procedures',
                ),
            ],
        ),
        new Post(uriTemplate: '/assay'),
        new Patch(uriTemplate: '/assay/{id}'),
        new Get(
            name: 'legacy_procedure_get',
            uriTemplate: '/procedures/{id}',
            normalizationContext: [
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ]),
        new GetCollection(
            name: 'legacy_procedure_get_collection',
            uriTemplate: '/procedures',
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['procedure:list'],
            ]
        ),
        new GetCollection(
            name: 'procedures',
            uriTemplate: '/experiments/{id}/procedures',
            uriVariables: [
                'id' => new Link(
                    fromClass: Experiment::class,
                    fromProperty: 'procedures',
                ),
            ],
        ),
        new Post(name: 'legacy_procedure_post', uriTemplate: '/procedures'),
        new Patch(name: 'legacy_procedure_patch', uriTemplate: '/procedures/{id}'),
    ],
    normalizationContext: ([
        AbstractNormalizer::GROUPS => ['procedure:read', 'query', 'query_collection', 'read:nested'],
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
    ]
    ),
    denormalizationContext: ([
        AbstractNormalizer::GROUPS => ['procedure:write'],
    ]
    ),
    graphQlOperations: [
        new Query(
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['query', 'enum:read'],
            ]
        ),
        new QueryCollection(normalizationContext: [
            AbstractNormalizer::GROUPS => ['query_collection', 'read:nested', 'enum:read'],
        ]),
        new Mutation(
            normalizationContext: [AbstractNormalizer::GROUPS => ['query_collection', 'enum:read']],
            denormalizationContext: [AbstractNormalizer::GROUPS => ['mutation']],
            name: 'create'
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'state' => 'exact'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap(['surgery' => Surgery::class, 'treatment' => Treatment::class, 'behavior_test' => Behavior::class])]
abstract class Procedure implements AccountAwareInterface, UserAwareInterface, ConnectedAppEntityInterface
{
    // btmp user and account here not in abstract class to solve nullable and migration issues
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected User $user;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    protected Account $account;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    private ?string $elaftwExternalId = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    private ?string $fair3rExternalId = null;

    #[ORM\Column(name: 'softmouse_external_id', type: 'string', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    private ?string $softmouseExternalId = null;

    #[ORM\Column(name: 'tecniplast_external_id', type: 'string', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    private ?string $tecniplastExternalId = null;

    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested', 'enum:read'])]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(types: 'https://schema.org/partOf')]
    #[Assert\NotNull]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Experiment::class, inversedBy: 'procedures')]
    #[Groups(['query', 'query_collection', 'read:nested', 'procedure:write'])]
    #[MaxDepth(5)]
    private Experiment $experiment;

    #[ApiProperty(types: 'https://schema.org/startTime')]
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'procedure:write', 'experiment.read', 'read:nested', 'enum:read'])]
    // #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write'])]
    private ?\DateTimeInterface $startAt;

    #[ApiProperty(types: 'https://schema.org/endTime')]
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'procedure:write', 'experiment.read', 'read:nested', 'enum:read'])]
    // #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write'])]
    private ?\DateTimeInterface $endAt = null;

    /**
     * Maybe we won't persist this property anymore, and we'll add a method to calculate it.
     * Duration in seconds.
     */
    #[ApiProperty(types: 'https://schema.org/duration')]
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    private ?float $duration = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(types: 'https://schema.org/name', )]
    #[Assert\NotBlank]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ApiProperty(types: 'https://schema.org/howPerformed')]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    private ?string $protocol = null;

    #[ApiProperty(types: 'https://schema.org/mainEntityOfPage')]
    //    #[Assert\Url]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    private ?string $protocolUri = null;

    #[ApiProperty(types: 'https://schema.org/status')]
    #[ORM\Column(type: 'string')]
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'procedure:write', 'read:nested', 'enum:read'])]
    #[Assert\Choice(choices: ['draft', 'planned', 'in_progress', 'completed', 'cancelled'])]
    private string $state = 'draft';

    /**
     * @var Collection<int, Subject>
     */
    #[Groups(['procedure:write', 'read:nested', 'enum:read'])]
    #[ORM\ManyToMany(targetEntity: Subject::class, mappedBy: 'procedures', cascade: ['persist'])]
    #[MaxDepth(5)]
    private Collection $subjects;

    public function __construct()
    {
        $this->subjects = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'read:nested'])]
    #[SerializedName('identifier')]
    public function getIsaIdentifier(): ?string
    {
        return $this->id?->toRfc4122();
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

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

    public function getSoftmouseExternalId(): ?string
    {
        return $this->softmouseExternalId;
    }

    public function setSoftmouseExternalId(?string $softmouseExternalId): self
    {
        $this->softmouseExternalId = $softmouseExternalId;

        return $this;
    }

    public function getExperiment(): Experiment
    {
        return $this->experiment;
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'read:nested'])]
    #[SerializedName('study')]
    #[MaxDepth(5)]
    public function getIsaStudy(): Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(Experiment $experiment): self
    {
        $this->experiment = $experiment;

        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    #[SerializedName('submissionDate')]
    public function getIsaSubmissionDate(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeInterface $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    #[SerializedName('publicReleaseDate')]
    public function getIsaPublicReleaseDate(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeInterface $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    #[Groups(['procedure:list'])]
    #[ApiProperty(iris: ['https://schema.org/name'])]
    public function getName(): string
    {
        return $this->name;
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'read:nested'])]
    #[SerializedName('title')]
    public function getIsaTitle(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    public function setProtocol(?string $protocol): self
    {
        $this->protocol = $protocol;

        return $this;
    }

    public function getProtocolUri(): ?string
    {
        return $this->protocolUri;
    }

    public function setProtocolUri(?string $protocolUri): self
    {
        $this->protocolUri = $protocolUri;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

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
            $subject->addProcedure($this);
        }
    }

    public function removeSubject(Subject $subject): void
    {
        if ($this->subjects->removeElement($subject)) {
            $subject->removeProcedure($this);
        }
    }

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\Column(name: 'procedure_type_enum', type: 'string', nullable: true, enumType: ProcedureType::class)]
    protected ?ProcedureType $procedureTypeEnum = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:list', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(readableLink: true)]
    public function getProcedureType(): ?ProcedureType
    {
        // Return the stored value if set, otherwise let child classes override
        return $this->procedureTypeEnum;
    }

    public function setProcedureType(?ProcedureType $procedureType): self
    {
        $this->procedureTypeEnum = $procedureType;

        return $this;
    }

    #[ApiProperty]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $surgeryName = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?InvasivenessType $invasivenessType = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $invasivenessDetails = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?User $actor = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $description = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?bool $hasAnesthesia = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $anesthesiaDetails = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?int $surgeryDuration = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?int $recoveryDuration = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $recoveryDetails = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $complications = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?TreatmentType $treatmentType = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?TreatmentCategory $category = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $drug = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?float $dose = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $doseUnit = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?TreatmentRoute $route = null;

    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $apparatus = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?string $context = null;
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write', 'read:nested', 'enum:read'])]
    #[ApiProperty]
    private ?float $lighting = null;

    public function getSurgeryName(): ?string
    {
        return $this->surgeryName;
    }

    public function setSurgeryName(?string $surgeryName): self
    {
        $this->surgeryName = $surgeryName;

        return $this;
    }

    public function getInvasivenessType(): ?InvasivenessType
    {
        return $this->invasivenessType;
    }

    public function setInvasivenessType(?InvasivenessType $invasivenessType): self
    {
        $this->invasivenessType = $invasivenessType;

        return $this;
    }

    public function getInvasivenessDetails(): ?string
    {
        return $this->invasivenessDetails;
    }

    public function setInvasivenessDetails(?string $invasivenessDetails): self
    {
        $this->invasivenessDetails = $invasivenessDetails;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

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

    public function getHasAnesthesia(): ?bool
    {
        return $this->hasAnesthesia;
    }

    public function setHasAnesthesia(?bool $hasAnesthesia): self
    {
        $this->hasAnesthesia = $hasAnesthesia;

        return $this;
    }

    public function getAnesthesiaDetails(): ?string
    {
        return $this->anesthesiaDetails;
    }

    public function setAnesthesiaDetails(?string $anesthesiaDetails): self
    {
        $this->anesthesiaDetails = $anesthesiaDetails;

        return $this;
    }

    public function getSurgeryDuration(): ?int
    {
        return $this->surgeryDuration;
    }

    public function setSurgeryDuration(?int $surgeryDuration): self
    {
        $this->surgeryDuration = $surgeryDuration;

        return $this;
    }

    public function getRecoveryDuration(): ?int
    {
        return $this->recoveryDuration;
    }

    public function setRecoveryDuration(?int $recoveryDuration): self
    {
        $this->recoveryDuration = $recoveryDuration;

        return $this;
    }

    public function getRecoveryDetails(): ?string
    {
        return $this->recoveryDetails;
    }

    public function setRecoveryDetails(?string $recoveryDetails): self
    {
        $this->recoveryDetails = $recoveryDetails;

        return $this;
    }

    public function getComplications(): ?string
    {
        return $this->complications;
    }

    public function setComplications(?string $complications): self
    {
        $this->complications = $complications;

        return $this;
    }

    public function getTreatmentType(): ?TreatmentType
    {
        return $this->treatmentType;
    }

    public function setTreatmentType(?TreatmentType $treatmentType): self
    {
        $this->treatmentType = $treatmentType;

        return $this;
    }

    public function getCategory(): ?TreatmentCategory
    {
        return $this->category;
    }

    public function setCategory(?TreatmentCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDrug(): ?string
    {
        return $this->drug;
    }

    public function setDrug(?string $drug): self
    {
        $this->drug = $drug;

        return $this;
    }

    public function getDose(): ?float
    {
        return $this->dose;
    }

    public function setDose(?float $dose): self
    {
        $this->dose = $dose;

        return $this;
    }

    public function getDoseUnit(): ?string
    {
        return $this->doseUnit;
    }

    public function setDoseUnit(?string $doseUnit): self
    {
        $this->doseUnit = $doseUnit;

        return $this;
    }

    public function getRoute(): ?TreatmentRoute
    {
        return $this->route;
    }

    public function setRoute(?TreatmentRoute $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getApparatus(): ?string
    {
        return $this->apparatus;
    }

    public function setApparatus(?string $apparatus): self
    {
        $this->apparatus = $apparatus;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getLighting(): ?float
    {
        return $this->lighting;
    }

    public function setLighting(?float $lighting): self
    {
        $this->lighting = $lighting;

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

    public function getTecniplastExternalId(): ?string
    {
        return $this->tecniplastExternalId;
    }

    public function setTecniplastExternalId(?string $tecniplastExternalId): self
    {
        $this->tecniplastExternalId = $tecniplastExternalId;

        return $this;
    }
}
