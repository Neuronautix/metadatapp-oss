<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\MortalityReason;
use App\State\Processor\MortalityRecordPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\MortalityRecordRepository::class)]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['mortality_record.read', 'read:nested'], AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    denormalizationContext: ['groups' => ['mortality_record.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            processor: MortalityRecordPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        'batch' => 'exact',
        'batch.id' => 'exact',
        'batch.line.id' => 'exact',
        'batch.pool.rack.room.id' => 'exact',
        'batch.pool.rack.room.plateau.id' => 'exact',
        'reason' => 'ipartial',
        'notes' => 'ipartial',
    ]
)]
#[ApiFilter(DateFilter::class, properties: ['date'])]
class MortalityRecord implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['mortality_record.read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ZebrafishBatch::class, inversedBy: 'mortalityRecords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['mortality_record.read', 'mortality_record.write', 'read:nested'])]
    #[MaxDepth(1)]
    private ZebrafishBatch $batch;

    #[ORM\Column(name: 'event_at', type: 'datetime_immutable')]
    #[Assert\NotNull]
    #[Groups(['mortality_record.read', 'mortality_record.write'])]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['mortality_record.read', 'mortality_record.write'])]
    private int $count;

    #[ORM\Column(type: 'string', enumType: MortalityReason::class)]
    #[Assert\NotBlank]
    #[Groups(['mortality_record.read', 'mortality_record.write'])]
    private MortalityReason $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['mortality_record.read', 'mortality_record.write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['mortality_record.read', 'mortality_record.write'])]
    #[MaxDepth(1)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getBatch(): ZebrafishBatch
    {
        return $this->batch;
    }

    public function setBatch(ZebrafishBatch $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getReason(): MortalityReason
    {
        return $this->reason;
    }

    public function setReason(MortalityReason $reason): self
    {
        $this->reason = $reason;

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

    public function getRecorder(): User
    {
        return $this->user;
    }

    public function setRecorder(User $recorder): self
    {
        $this->user = $recorder;

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
