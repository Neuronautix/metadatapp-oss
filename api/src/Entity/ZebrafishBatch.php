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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\State\Processor\ZebrafishBatchPersistProcessor;
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
    normalizationContext: ['groups' => ['zebrafish_batch.read', 'read:nested', 'enum:read'], AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    denormalizationContext: ['groups' => ['zebrafish_batch.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            processor: ZebrafishBatchPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Put(
            processor: ZebrafishBatchPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            processor: ZebrafishBatchPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        'line' => 'exact',
        'line.id' => 'exact',
        'line.name' => 'ipartial',
        'pool' => 'exact',
        'pool.id' => 'exact',
        'pool.position' => 'ipartial',
        'pool.rack.id' => 'exact',
        'pool.rack.room' => 'exact',
        'pool.rack.room.id' => 'exact',
        'pool.rack.room.plateau' => 'exact',
        'pool.rack.room.plateau.id' => 'exact',
        'lifecycleStatus' => 'exact',
    ]
)]
#[ApiFilter(DateFilter::class, properties: ['birthDate'])]
class ZebrafishBatch implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['zebrafish_batch.read', 'read:nested'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ZebrafishLine::class, inversedBy: 'batches')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write', 'read:nested'])]
    #[MaxDepth(1)]
    private ZebrafishLine $line;

    #[ORM\ManyToOne(targetEntity: Pool::class, inversedBy: 'batches')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    #[MaxDepth(1)]
    private Pool $pool;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    private \DateTimeImmutable $birthDate;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    private int $maleCount = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    private int $femaleCount = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    private int $unknownSexCount = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write'])]
    private int $totalPopulation = 0;

    #[ORM\Column(type: 'string', enumType: ZebrafishBatchLifecycleStatus::class)]
    #[Groups(['zebrafish_batch.read', 'zebrafish_batch.write', 'read:nested', 'enum:read'])]
    private ZebrafishBatchLifecycleStatus $lifecycleStatus = ZebrafishBatchLifecycleStatus::ACTIVE;

    /**
     * @var Collection<int, MortalityRecord>
     */
    #[ORM\OneToMany(mappedBy: 'batch', targetEntity: MortalityRecord::class)]
    #[ORM\OrderBy(['date' => 'ASC', 'id' => 'ASC'])]
    private Collection $mortalityRecords;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function __construct()
    {
        $this->mortalityRecords = new ArrayCollection();
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

    public function getLine(): ZebrafishLine
    {
        return $this->line;
    }

    public function setLine(ZebrafishLine $line): self
    {
        $this->line = $line;

        return $this;
    }

    public function getPool(): Pool
    {
        return $this->pool;
    }

    public function setPool(Pool $pool): self
    {
        $this->pool = $pool;

        return $this;
    }

    public function getBirthDate(): \DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeImmutable $birthDate): self
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getMaleCount(): int
    {
        return $this->maleCount;
    }

    public function setMaleCount(int $maleCount): self
    {
        $this->maleCount = $maleCount;

        return $this;
    }

    public function getFemaleCount(): int
    {
        return $this->femaleCount;
    }

    public function setFemaleCount(int $femaleCount): self
    {
        $this->femaleCount = $femaleCount;

        return $this;
    }

    public function getUnknownSexCount(): int
    {
        return $this->unknownSexCount;
    }

    public function setUnknownSexCount(int $unknownSexCount): self
    {
        $this->unknownSexCount = $unknownSexCount;

        return $this;
    }

    public function getTotalPopulation(): int
    {
        return $this->totalPopulation;
    }

    public function setTotalPopulation(int $totalPopulation): self
    {
        $this->totalPopulation = $totalPopulation;

        return $this;
    }

    public function getLifecycleStatus(): ZebrafishBatchLifecycleStatus
    {
        return $this->lifecycleStatus;
    }

    public function setLifecycleStatus(ZebrafishBatchLifecycleStatus $lifecycleStatus): self
    {
        $this->lifecycleStatus = $lifecycleStatus;

        return $this;
    }

    /**
     * @return Collection<int, MortalityRecord>
     */
    public function getMortalityRecords(): Collection
    {
        return $this->mortalityRecords;
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
