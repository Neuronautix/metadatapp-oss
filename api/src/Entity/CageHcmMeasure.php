<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Enum\HcmDataType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Represents HCM measurements for a cage.
 */
#[ORM\Entity]
#[ApiResource]
class CageHcmMeasure implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(readable: false)]
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Cage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Cage $cage;

    #[ORM\ManyToOne(targetEntity: Variable::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Variable $variable;

    #[ORM\Column(name: 'dataType', type: 'string', enumType: HcmDataType::class)]
    private HcmDataType $dataType;

    // Check if this is the right type, probably should be a string, taht could be a numeric string, butmaybe a string or ???depends of the datatype of variables
    #[ORM\Column(type: 'float')]
    private float $value;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $recordedAt;

    #[ORM\ManyToOne(targetEntity: HomeCageMonitoring::class)]
    #[ORM\JoinColumn(nullable: false)]
    private HomeCageMonitoring $homeCageMonitoring;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $sourceTaskId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $sourceAppCode = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCage(): Cage
    {
        return $this->cage;
    }

    public function setCage(Cage $cage): self
    {
        $this->cage = $cage;

        return $this;
    }

    public function getVariable(): Variable
    {
        return $this->variable;
    }

    public function setVariable(Variable $variable): self
    {
        $this->variable = $variable;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getRecordedAt(): \DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): self
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getHomeCageMonitoring(): HomeCageMonitoring
    {
        return $this->homeCageMonitoring;
    }

    public function setHomeCageMonitoring(HomeCageMonitoring $homeCageMonitoring): self
    {
        $this->homeCageMonitoring = $homeCageMonitoring;

        return $this;
    }

    public function getDataType(): HcmDataType
    {
        return $this->dataType;
    }

    public function setDataType(HcmDataType $dataType): self
    {
        $this->dataType = $dataType;

        return $this;
    }

    public function getSourceTaskId(): ?string
    {
        return $this->sourceTaskId;
    }

    public function setSourceTaskId(?string $sourceTaskId): self
    {
        $this->sourceTaskId = $sourceTaskId;

        return $this;
    }

    public function getSourceAppCode(): ?string
    {
        return $this->sourceAppCode;
    }

    public function setSourceAppCode(?string $sourceAppCode): self
    {
        $this->sourceAppCode = $sourceAppCode;

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
