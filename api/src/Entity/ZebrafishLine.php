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
use App\Enum\ZebrafishLineStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['zebrafish_line.read', 'read:nested', 'enum:read']],
    denormalizationContext: ['groups' => ['zebrafish_line.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial', 'genotype' => 'ipartial', 'purpose' => 'ipartial', 'status' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class ZebrafishLine implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['zebrafish_line.read', 'read:nested'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['zebrafish_line.read', 'zebrafish_line.write', 'read:nested'])]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['zebrafish_line.read', 'zebrafish_line.write', 'read:nested'])]
    private ?string $genotype = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['zebrafish_line.read', 'zebrafish_line.write'])]
    private ?string $purpose = null;

    #[ORM\Column(type: 'string', enumType: ZebrafishLineStatus::class)]
    #[Groups(['zebrafish_line.read', 'zebrafish_line.write', 'read:nested', 'enum:read'])]
    private ZebrafishLineStatus $status = ZebrafishLineStatus::ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['zebrafish_line.read', 'zebrafish_line.write'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, ZebrafishBatch>
     */
    #[ORM\OneToMany(mappedBy: 'line', targetEntity: ZebrafishBatch::class)]
    private Collection $batches;

    /**
     * @var Collection<int, CryoRecord>
     */
    #[ORM\OneToMany(mappedBy: 'line', targetEntity: CryoRecord::class)]
    #[ORM\OrderBy(['storageDate' => 'DESC', 'id' => 'DESC'])]
    private Collection $cryoRecords;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->batches = new ArrayCollection();
        $this->cryoRecords = new ArrayCollection();
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

    public function getGenotype(): ?string
    {
        return $this->genotype;
    }

    public function setGenotype(?string $genotype): self
    {
        $this->genotype = $genotype;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getStatus(): ZebrafishLineStatus
    {
        return $this->status;
    }

    public function setStatus(ZebrafishLineStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, ZebrafishBatch>
     */
    public function getBatches(): Collection
    {
        return $this->batches;
    }

    /**
     * @return Collection<int, CryoRecord>
     */
    public function getCryoRecords(): Collection
    {
        return $this->cryoRecords;
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
