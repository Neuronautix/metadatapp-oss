<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['pool.read', 'read:nested', 'enum:read'], AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    denormalizationContext: ['groups' => ['pool.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        'position' => 'ipartial',
        'status' => 'exact',
        'rack' => 'exact',
        'rack.id' => 'exact',
        'rack.room' => 'exact',
        'rack.room.id' => 'exact',
        'rack.room.plateau' => 'exact',
        'rack.room.plateau.id' => 'exact',
        'batches.lifecycleStatus' => 'exact',
        'batches.line.id' => 'exact',
    ]
)]
class Pool implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['pool.read', 'read:nested'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Rack::class, inversedBy: 'pools')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['pool.read', 'pool.write', 'read:nested'])]
    private Rack $rack;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Groups(['pool.read', 'pool.write', 'read:nested'])]
    private string $position;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['pool.read', 'pool.write'])]
    private int $capacity;

    #[ORM\Column(type: 'string', enumType: PoolStatus::class)]
    #[Groups(['pool.read', 'pool.write', 'read:nested', 'enum:read'])]
    private PoolStatus $status = PoolStatus::AVAILABLE;

    /**
     * @var Collection<int, ZebrafishBatch>
     */
    #[ORM\OneToMany(mappedBy: 'pool', targetEntity: ZebrafishBatch::class)]
    private Collection $batches;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function __construct()
    {
        $this->batches = new ArrayCollection();
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

    public function getRack(): Rack
    {
        return $this->rack;
    }

    public function setRack(Rack $rack): self
    {
        $this->rack = $rack;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getStatus(): PoolStatus
    {
        return $this->status;
    }

    public function setStatus(PoolStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, ZebrafishBatch>
     */
    public function getBatches(): Collection
    {
        return $this->batches;
    }

    #[Groups(['pool.read'])]
    #[MaxDepth(1)]
    public function getActiveBatch(): ?ZebrafishBatch
    {
        foreach ($this->batches as $batch) {
            if (\in_array($batch->getLifecycleStatus(), [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED], true)) {
                return $batch;
            }
        }

        return null;
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
