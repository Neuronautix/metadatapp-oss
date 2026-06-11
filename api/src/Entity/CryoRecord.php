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
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Enum\CryoRecordStatus;
use App\State\Processor\CryoRecordPersistProcessor;
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
    normalizationContext: ['groups' => ['cryo_record.read', 'read:nested', 'enum:read'], AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    denormalizationContext: ['groups' => ['cryo_record.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            uriTemplate: '/zefix/lines/{lineID}/cryo-records',
            uriVariables: [
                'lineID' => new Link(
                    fromClass: ZebrafishLine::class,
                    fromProperty: 'cryoRecords',
                ),
            ],
            processor: CryoRecordPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'line' => 'exact',
    'line.id' => 'exact',
    'line.name' => 'ipartial',
    'storageLocation' => 'ipartial',
    'protocolUsed' => 'ipartial',
    'notes' => 'ipartial',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['storageDate'])]
class CryoRecord implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['cryo_record.read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ZebrafishLine::class, inversedBy: 'cryoRecords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cryo_record.read', 'cryo_record.write', 'read:nested'])]
    #[MaxDepth(1)]
    private ?ZebrafishLine $line = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['cryo_record.read', 'cryo_record.write'])]
    private string $storageLocation;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['cryo_record.read', 'cryo_record.write'])]
    private string $protocolUsed;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['cryo_record.read', 'cryo_record.write'])]
    private \DateTimeImmutable $storageDate;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cryo_record.read', 'cryo_record.write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', enumType: CryoRecordStatus::class)]
    #[Groups(['cryo_record.read', 'cryo_record.write', 'enum:read'])]
    private CryoRecordStatus $status = CryoRecordStatus::STORED;

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

    public function getLine(): ?ZebrafishLine
    {
        return $this->line;
    }

    public function setLine(?ZebrafishLine $line): self
    {
        $this->line = $line;

        return $this;
    }

    public function getStorageLocation(): string
    {
        return $this->storageLocation;
    }

    public function setStorageLocation(string $storageLocation): self
    {
        $this->storageLocation = $storageLocation;

        return $this;
    }

    public function getProtocolUsed(): string
    {
        return $this->protocolUsed;
    }

    public function setProtocolUsed(string $protocolUsed): self
    {
        $this->protocolUsed = $protocolUsed;

        return $this;
    }

    public function getStorageDate(): \DateTimeImmutable
    {
        return $this->storageDate;
    }

    public function setStorageDate(\DateTimeImmutable $storageDate): self
    {
        $this->storageDate = $storageDate;

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

    public function getStatus(): CryoRecordStatus
    {
        return $this->status;
    }

    public function setStatus(CryoRecordStatus $status): self
    {
        $this->status = $status;

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
