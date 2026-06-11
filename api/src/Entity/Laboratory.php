<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
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

/**
 * Represents a Lab within an Account.
 */
#[ORM\Entity]
#[ApiResource(
    types: 'https://schema.org/Organization',
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['laboratory.read', 'project.read', 'nested.read', 'enum:read'],
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['laboratory.write'],
    ],
    graphQlOperations: [
        new Query(normalizationContext: ['groups' => ['query']]),
        new QueryCollection(normalizationContext: ['groups' => ['query_collection']]),
        new Mutation(
            normalizationContext: ['groups' => ['query_collection']], denormalizationContext: ['groups' => ['mutation']], name: 'create'
        ),
        new Mutation(
            normalizationContext: ['groups' => ['query_collection']], denormalizationContext: ['groups' => ['mutation']], name: 'update'
        ),
    ]
)]
#[ApiResource()]
class Laboratory implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['laboratory.read', 'query', 'query_collection', 'read:nested', 'enum:read'])]
    private ?Uuid $id = null;

    #[ApiProperty(types: 'https://schema.org/name')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['laboratory.read', 'laboratory.write', 'query', 'query_collection', 'mutation', 'read:nested', 'enum:read'])]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['laboratory.read', 'laboratory.write', 'query', 'query_collection', 'mutation'])]
    #[ApiProperty(readable: false)]
    private Account $account;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'laboratories', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'laboratory_project')]
    #[Groups(['laboratory.read', 'laboratory.write', 'query', 'query_collection', 'mutation'])]
    #[MaxDepth(3)]
    private Collection $projects;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): void
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
        }
    }

    public function removeProject(Project $project): void
    {
        $this->projects->removeElement($project);
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
