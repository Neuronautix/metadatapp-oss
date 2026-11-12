<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents time series data collected during experiments.
 */
#[ORM\Entity]
#[ApiResource]
class TimeSeries implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ApiProperty(types: 'https://schema.org/partOf')]
    #[ORM\ManyToOne(targetEntity: Experiment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Experiment $experiment;

    #[ApiProperty(types: 'https://schema.org/variableMeasured')]
    #[ORM\ManyToOne(targetEntity: Variable::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Variable $variable;

    #[ApiProperty(types: 'https://schema.org/startTime')]
    #[Assert\NotNull]
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $recordedAt;

    #[ApiProperty(types: 'https://schema.org/value')]
    #[ORM\Column(type: 'decimal', precision: 10, scale: 5)]
    private string $value;

    #[ApiProperty(types: 'https://schema.org/unitCode')]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null;

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

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getExperiment(): Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(Experiment $experiment): self
    {
        $this->experiment = $experiment;

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

    public function getRecordedAt(): \DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): self
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

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
