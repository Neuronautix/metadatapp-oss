<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A saved column-mapping configuration that can be reused across import sessions.
 */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/curation/mapping-templates',
        ),
        new Post(
            uriTemplate: '/curation/mapping-templates',
        ),
        new Delete(
            uriTemplate: '/curation/mapping-templates/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['mapping_template.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['mapping_template.write'],
    ],
)]
class MappingTemplate implements AccountAwareInterface, UserAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['mapping_template.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['mapping_template.read', 'mapping_template.write'])]
    private string $name;

    /**
     * Serialised array of {sourceColumn, targetEntity, targetProperty} objects.
     *
     * @var array<int, array{sourceColumn: string, targetEntity: string, targetProperty: string}>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['mapping_template.read', 'mapping_template.write'])]
    private array $mappings = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['mapping_template.read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    /**
     * @return array<int, array{sourceColumn: string, targetEntity: string, targetProperty: string}>
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * @param array<int, array{sourceColumn: string, targetEntity: string, targetProperty: string}> $mappings
     */
    public function setMappings(array $mappings): self
    {
        $this->mappings = $mappings;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
