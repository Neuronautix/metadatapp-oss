<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Mcp\State\DoctrineListToolProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[McpTool(
    name: 'get_project',
    description: 'Retrieve details for a specific project by its UUID.',
)]
#[McpTool(
    name: 'get_investigation',
    description: 'Retrieve details for a specific investigation by its UUID.',
)]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/investigation/{id}'),
        new GetCollection(uriTemplate: '/investigation'),
        new Post(uriTemplate: '/investigation'),
        new Put(uriTemplate: '/investigation/{id}'),
        new Patch(uriTemplate: '/investigation/{id}'),
        new Delete(uriTemplate: '/investigation/{id}'),
        new Get(name: 'legacy_project_get', uriTemplate: '/projects/{id}'),
        new GetCollection(name: 'legacy_project_get_collection', uriTemplate: '/projects'),
        new Post(name: 'legacy_project_post', uriTemplate: '/projects'),
        new Put(name: 'legacy_project_put', uriTemplate: '/projects/{id}'),
        new Patch(name: 'legacy_project_patch', uriTemplate: '/projects/{id}'),
        new Delete(name: 'legacy_project_delete', uriTemplate: '/projects/{id}'),
    ],
    mcp: [
        'list_investigations' => new McpTool(
            name: 'list_investigations',
            description: 'List and search for investigations.',
            uriTemplate: '/investigation',
            provider: DoctrineListToolProvider::class,
        ),
        'list_projects' => new McpTool(
            name: 'list_projects',
            description: 'List and search for projects.',
            uriTemplate: '/projects',
            provider: DoctrineListToolProvider::class,
        ),
        'create_project' => new McpTool(
            name: 'create_project',
            description: 'Create a new project.',
            method: 'POST',
            uriTemplate: '/projects',
        ),
    ],
    graphQlOperations: [
        new Query(normalizationContext: ['groups' => ['project.read', 'read:nested', 'enum:read']]),
        new QueryCollection(normalizationContext: ['groups' => ['project.read', 'read:nested', 'enum:read']]),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new Mutation(name: 'delete'),
    ],
    normalizationContext: [
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        'groups' => ['project.read', 'read:nested', 'enum:read'],
    ],
    denormalizationContext: [
        'groups' => ['project.write'],
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial'])]
#[ApiFilter(DateFilter::class, properties: ['startAt'])]
#[ApiFilter(DateFilter::class, properties: ['endAt'])]
#[ORM\Entity]
class Project implements AccountAwareInterface, UserAwareInterface, ConnectedAppEntityInterface
{
    #[ApiProperty(identifier: true, types: 'https://schema.org/identifier')]
    #[Groups(['project.read', 'query', 'query_collection'])]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    #[ApiProperty(types: 'https://schema.org/name', iris: ['https://schema.org/name'])]
    #[Assert\NotBlank]
    #[Groups(['project.read', 'laboratory.read', 'project.write', 'query', 'query_collection', 'experiment.read'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $goal = null;

    #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startAt = null;

    #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    private User $user;

    //    #[Assert\Url]
    #[Groups(['project.read', 'project.write', 'query', 'query_collection', 'fair3rDatastore'])]
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $repositoryLink = null;

    /**
     * @var Collection<int, Laboratory>
     */
    #[Groups(['project.read', 'query', 'query_collection', 'read:nested', 'enum:read', 'nested:read', 'project:write'])]
    #[ORM\ManyToMany(targetEntity: Laboratory::class, mappedBy: 'projects')]
    #[MaxDepth(5)]
    private Collection $laboratories;

    /**
     * @var Collection<int, Experiment>
     */
    // #[Groups(['project.read', 'project.write', 'query', 'query_collection'])]
    #[Groups(['read:nested', 'project.read', 'project:write'])]
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Experiment::class)]
    #[MaxDepth(5)]
    private Collection $experiments;

    #[Groups('fair3rDatastore')]
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    private ?string $elaftwExternalId = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    #[Groups(['project.read', 'query', 'query_collection'])]
    private ?string $fair3rExternalId = null;

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

    public function __construct()
    {
        $this->experiments = new ArrayCollection();
        $this->laboratories = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    #[Groups(['project.read', 'query', 'query_collection'])]
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

    #[Groups(['project.read', 'query', 'query_collection'])]
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

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    #[Groups(['project.read', 'query', 'query_collection'])]
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

    #[Groups(['project.read', 'query', 'query_collection'])]
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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
     * @return Collection<int, Laboratory>
     */
    public function getLaboratories(): Collection
    {
        return $this->laboratories;
    }

    /**
     * @param Collection<int, Laboratory> $laboratories
     */
    public function setLaboratories(Collection $laboratories): self
    {
        $this->laboratories = $laboratories;

        return $this;
    }

    // add remove
    public function addLaboratory(Laboratory $laboratory): void
    {
        if (!$this->laboratories->contains($laboratory)) {
            $this->laboratories->add($laboratory);
        }
    }

    public function removeLaboratory(Laboratory $laboratory): void
    {
        $this->laboratories->removeElement($laboratory);
    }

    /**
     * @return Collection<int, Experiment>
     */
    public function getExperiments(): Collection
    {
        return $this->experiments;
    }

    /**
     * @return Collection<int, Experiment>
     */
    #[Groups(['project.read', 'query', 'query_collection', 'read:nested'])]
    #[SerializedName('studies')]
    #[MaxDepth(5)]
    public function getIsaStudies(): Collection
    {
        return $this->experiments;
    }

    /**
     * @param Collection<int, Experiment> $experiments
     */
    public function setExperiments(Collection $experiments): void
    {
        $this->experiments = $experiments;
    }

    // add remove
    public function addExperiment(Experiment $experiment): void
    {
        if (!$this->experiments->contains($experiment)) {
            $this->experiments->add($experiment);
        }
    }

    public function removeExperiment(Experiment $experiment): void
    {
        $this->experiments->removeElement($experiment);
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
