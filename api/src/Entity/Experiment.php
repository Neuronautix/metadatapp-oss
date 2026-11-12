<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\UuidFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\ExperimentType;
use App\Mcp\State\DoctrineListToolProvider;
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
 * Represents an Experiment within a Project.
 */
#[ORM\Entity]
#[McpTool(
    name: 'get_experiment',
    description: 'Retrieve details for a specific experiment by its UUID.',
)]
#[McpTool(
    name: 'get_study',
    description: 'Retrieve details for a specific study by its UUID.',
)]
#[ApiResource(
    types: 'https://schema.org/Thing', // @semanticTeam: please check if there is a more specific type
    operations: [
        new Get(uriTemplate: '/study/{id}'),
        new Post(uriTemplate: '/study'),
        //        new Put(processor: ConnectedAppSyncProcessor::class),
        //        new Patch(processor: ConnectedAppSyncProcessor::class),
        //        new Delete(),
        //        new GetCollection(provider: ConnectedAppSyncTriggerProvider::class),
        new Put(uriTemplate: '/study/{id}'),
        new Patch(uriTemplate: '/study/{id}'),
        new Delete(uriTemplate: '/study/{id}'),
        new GetCollection(
            uriTemplate: '/study',
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['experiment.collection', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: [
                'nameContains' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on study name.',
                ),
                'goalContainsCaseSensitive' => new QueryParameter(
                    filter: new PartialSearchFilter(true),
                    property: 'goal',
                    description: 'Case-sensitive partial search on study goal.',
                ),
                'project' => new QueryParameter(
                    filter: new IriFilter(),
                    property: 'project',
                    description: 'Filter studies by investigation IRI.',
                ),
                'id' => new QueryParameter(
                    filter: new UuidFilter(),
                    property: 'id',
                    description: 'Filter studies by their UUID.',
                ),
                'projectId' => new QueryParameter(
                    filter: new UuidFilter(),
                    property: 'project.id',
                    description: 'Filter studies by nested investigation UUID.',
                ),
                'projectIdComparison' => new QueryParameter(
                    filter: new ComparisonFilter(new UuidFilter()),
                    property: 'project.id',
                    description: 'Compare nested investigation UUID using gt/gte/lt/lte/ne operators.',
                ),
            ],
        ),
        new Get(name: 'legacy_experiment_get', uriTemplate: '/experiments/{id}'),
        new Post(name: 'legacy_experiment_post', uriTemplate: '/experiments'),
        new Put(name: 'legacy_experiment_put', uriTemplate: '/experiments/{id}'),
        new Patch(name: 'legacy_experiment_patch', uriTemplate: '/experiments/{id}'),
        new Delete(name: 'legacy_experiment_delete', uriTemplate: '/experiments/{id}'),
        new GetCollection(
            name: 'legacy_experiment_get_collection',
            uriTemplate: '/experiments',
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['experiment.collection', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: [
                'nameContains' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on experiment name.',
                ),
                'goalContainsCaseSensitive' => new QueryParameter(
                    filter: new PartialSearchFilter(true),
                    property: 'goal',
                    description: 'Case-sensitive partial search on experiment goal.',
                ),
                'project' => new QueryParameter(
                    filter: new IriFilter(),
                    property: 'project',
                    description: 'Filter experiments by project IRI.',
                ),
                'id' => new QueryParameter(
                    filter: new UuidFilter(),
                    property: 'id',
                    description: 'Filter experiments by their UUID.',
                ),
                'projectId' => new QueryParameter(
                    filter: new UuidFilter(),
                    property: 'project.id',
                    description: 'Filter experiments by nested project UUID.',
                ),
                'projectIdComparison' => new QueryParameter(
                    filter: new ComparisonFilter(new UuidFilter()),
                    property: 'project.id',
                    description: 'Compare nested project UUID using gt/gte/lt/lte/ne operators.',
                ),
            ],
        ),
    ],
    mcp: [
        'list_studies' => new McpTool(
            name: 'list_studies',
            description: 'List and search for studies with filters (name, goal, investigation).',
            uriTemplate: '/study',
            provider: DoctrineListToolProvider::class,
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['experiment.collection', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: new Parameters([
                'nameContains' => new QueryParameter(
                    key: 'nameContains',
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on experiment name.',
                ),
                'goalContainsCaseSensitive' => new QueryParameter(
                    key: 'goalContainsCaseSensitive',
                    filter: new PartialSearchFilter(true),
                    property: 'goal',
                    description: 'Case-sensitive partial search on experiment goal.',
                ),
                'project' => new QueryParameter(
                    key: 'project',
                    filter: new IriFilter(),
                    property: 'project',
                    description: 'Filter studies by investigation IRI.',
                ),
                'id' => new QueryParameter(
                    key: 'id',
                    filter: new UuidFilter(),
                    property: 'id',
                    description: 'Filter studies by their UUID.',
                ),
                'projectId' => new QueryParameter(
                    key: 'projectId',
                    filter: new UuidFilter(),
                    property: 'project.id',
                    description: 'Filter studies by nested investigation UUID.',
                ),
                'projectIdComparison' => new QueryParameter(
                    key: 'projectIdComparison',
                    filter: new ComparisonFilter(new UuidFilter()),
                    property: 'project.id',
                    description: 'Compare nested investigation UUID using gt/gte/lt/lte/ne operators.',
                ),
            ]),
        ),
        'list_experiments' => new McpTool(
            name: 'list_experiments',
            description: 'List and search for experiments with filters (name, goal, project).',
            uriTemplate: '/experiments',
            provider: DoctrineListToolProvider::class,
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['experiment.collection', 'enum:read'],
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ],
            parameters: new Parameters([
                'nameContains' => new QueryParameter(
                    key: 'nameContains',
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    description: 'Case-insensitive partial search on experiment name.',
                ),
                'goalContainsCaseSensitive' => new QueryParameter(
                    key: 'goalContainsCaseSensitive',
                    filter: new PartialSearchFilter(true),
                    property: 'goal',
                    description: 'Case-sensitive partial search on experiment goal.',
                ),
                'project' => new QueryParameter(
                    key: 'project',
                    filter: new IriFilter(),
                    property: 'project',
                    description: 'Filter experiments by project IRI.',
                ),
                'id' => new QueryParameter(
                    key: 'id',
                    filter: new UuidFilter(),
                    property: 'id',
                    description: 'Filter experiments by their UUID.',
                ),
                'projectId' => new QueryParameter(
                    key: 'projectId',
                    filter: new UuidFilter(),
                    property: 'project.id',
                    description: 'Filter experiments by nested project UUID.',
                ),
                'projectIdComparison' => new QueryParameter(
                    key: 'projectIdComparison',
                    filter: new ComparisonFilter(new UuidFilter()),
                    property: 'project.id',
                    description: 'Compare nested project UUID using gt/gte/lt/lte/ne operators.',
                ),
            ]),
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['experiment.item', 'enum:read'],
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['experiment.write', 'project.write', 'fair3rDatastore'],
    ],
    graphQlOperations: [
        new Query(normalizationContext: ['groups' => ['query']]),
        new QueryCollection(normalizationContext: ['groups' => ['query_collection']]),
        new Mutation(
            normalizationContext: ['groups' => ['query_collection']],
            denormalizationContext: ['groups' => ['mutation']],
            name: 'create'
        ),
    ]
    //    operations: [
    //        new Get(),
    //        new Post(),
    //        new Delete(),
    //        new GetCollection(), // todo uncomment to trigger the sync when we display the list of experiments
    //    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial'])]
#[ApiFilter(SearchFilter::class, properties: ['goal' => 'ipartial'])]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'iexact'])]
#[ApiFilter(DateFilter::class, properties: ['startAt'])]
#[ApiFilter(DateFilter::class, properties: ['endAt'])]
class Experiment implements AccountAwareInterface, UserAwareInterface, ConnectedAppEntityInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection', 'project.read', 'project.write', 'fair3rDatastore'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(types: 'https://schema.org/name', iris: ['https://schema.org/name'])]
    #[Assert\NotBlank()]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'project.write', 'query_collection', 'mutation', 'fair3rDatastore'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'query_collection', 'mutation'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $goal = null;

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'query_collection', 'mutation', 'enum:read'])]
    #[ORM\Column(name: 'type', type: 'string', length: 50, enumType: ExperimentType::class)]
    private ExperimentType $type = ExperimentType::TypeA;

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'project.write ', 'query', 'query_collection', 'mutation'])]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $protocol = null;

    #[Assert\NotNull]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'query_collection', 'mutation'])]
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startAt;

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'query_collection', 'mutation'])]
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'experiment.write', 'project.read', 'query', 'query_collection', 'mutation'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'experiments')]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'experiment.write', 'mutation'])]
    private ?Project $project = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'project.read', 'query_collection', 'fair3rDatastore'])] // useless 'fair3rDatastore'
    private ?string $repositoryLink = null; // naming Url ???

    #[Groups(['fakeAppExperiment'])]
    public function getProjectId(): ?string
    {
        return $this->getProject()?->getExternalId();
    }

    /**
     * Returns the records for the Fair3r Datastore.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Groups(['fair3rDatastore'])]
    public function getRecords(): array
    {
        $records = [];
        foreach ($this->getProcedures() as $procedure) {
            foreach ($procedure->getSubjects() as $subject) {
                foreach ($subject->getWeightMeasurements() as $weightMeasurement) {
                    $records[] = [
                        'subject_id' => $subject->getExternalId(),
                        'weight' => $weightMeasurement->getWeight(),
                        'date' => $weightMeasurement->getMeasuredAt()->format('Y-m-d'),
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * @var Collection<int, Procedure>
     */
    #[ORM\OneToMany(targetEntity: Procedure::class, mappedBy: 'experiment', cascade: ['persist'])]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])] // ✅ Added GraphQL groups
    #[MaxDepth(5)]
    private Collection $procedures;

    /**
     * @var Collection<int, ConnectedResourceLink>
     */
    #[ORM\OneToMany(targetEntity: ConnectedResourceLink::class, mappedBy: 'experiment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['experiment.read', 'experiment.item'])]
    private Collection $connectedResourceLinks;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['experiment.read', 'experiment.item'])]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null; // SM

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'experiment.write', 'query', 'query_collection'])]
    private ?string $elaftwExternalId = null;

    /**
     * Raw eLabFTW metadata / extra fields payload.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'elabftw_metadata', type: 'json', nullable: true, options: ['jsonb' => true])]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.write', 'query', 'query_collection'])]
    private ?array $elabftwMetadata = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'query', 'query_collection'])] // ✅ Added GraphQL groups
    private ?string $fair3rExternalId = null;

    #[ORM\Column(name: 'softmouse_external_id', type: 'string', nullable: true)]
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'query', 'query_collection'])] // ✅ Added GraphQL groups
    private ?string $softmouseExternalId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $datasetId = null;

    public function __construct()
    {
        $this->procedures = new ArrayCollection();
        $this->connectedResourceLinks = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
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

    public function getName(): string
    {
        return $this->name;
    }

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
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

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function setGoal(?string $goal): self
    {
        $this->goal = $goal;

        return $this;
    }

    public function getType(): ExperimentType
    {
        return $this->type;
    }

    public function setType(ExperimentType $type): self
    {
        $this->type = $type;

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

    public function getStartAt(): \DateTimeInterface
    {
        return $this->startAt;
    }

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
    #[SerializedName('submissionDate')]
    public function getIsaSubmissionDate(): \DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeInterface $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
    #[SerializedName('investigation')]
    #[MaxDepth(5)]
    public function getIsaInvestigation(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getRepositoryLink(): ?string
    {
        return $this->repositoryLink;
    }

    public function setRepositoryLink(?string $repositoryLink): self
    {
        $this->repositoryLink = $repositoryLink;

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
     * @return Collection<int, Procedure>
     */
    #[Groups(['experiment.read', 'experiment.item', 'experiment.collection', 'read:nested', 'query', 'query_collection'])]
    #[SerializedName('assays')]
    #[MaxDepth(5)]
    public function getIsaAssays(): Collection
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
            $this->procedures->add($procedure);
            $procedure->setExperiment($this);
            $procedure->setAccount($this->getAccount());
            $procedure->setUser($this->getUser());
        }
    }

    public function removeProcedure(Procedure $procedure): self
    {
        $this->procedures->removeElement($procedure);

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

    /**
     * @return array<string, mixed>|null
     */
    public function getElabftwMetadata(): ?array
    {
        return $this->elabftwMetadata;
    }

    /**
     * @param array<string, mixed>|null $elabftwMetadata
     */
    public function setElabftwMetadata(?array $elabftwMetadata): self
    {
        $this->elabftwMetadata = $elabftwMetadata;

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

    // todo remove dataset;
    public function getDatasetId(): ?string
    {
        return $this->datasetId;
    }

    public function setDatasetId(?string $datasetId): void
    {
        $this->datasetId = $datasetId;
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

    /**
     * @return Collection<int, ConnectedResourceLink>
     */
    public function getConnectedResourceLinks(): Collection
    {
        return $this->connectedResourceLinks;
    }

    public function addConnectedResourceLink(ConnectedResourceLink $link): self
    {
        if (!$this->connectedResourceLinks->contains($link)) {
            $this->connectedResourceLinks->add($link);
            $link->setExperiment($this);
            $link->setAccount($this->getAccount());
        }

        return $this;
    }

    public function removeConnectedResourceLink(ConnectedResourceLink $link): self
    {
        $this->connectedResourceLinks->removeElement($link);

        return $this;
    }
}
