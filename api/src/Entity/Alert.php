<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AlertEntityType;
use App\Enum\AlertSeverity;
use App\Enum\AlertStatus;
use App\Enum\AlertType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: \App\Repository\AlertRepository::class)]
class Alert implements AccountAwareInterface
{
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', enumType: AlertType::class)]
    private AlertType $type;

    #[ORM\Column(type: 'string', enumType: AlertSeverity::class)]
    private AlertSeverity $severity;

    #[ORM\Column(type: 'string', enumType: AlertStatus::class)]
    private AlertStatus $status = AlertStatus::ACTIVE;

    #[ORM\Column(type: 'string', enumType: AlertEntityType::class)]
    private AlertEntityType $affectedEntityType;

    #[ORM\Column(type: 'text')]
    private string $recommendedAction;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: System::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?System $system = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: ZebrafishBatch::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ZebrafishBatch $batch = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
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

    public function getType(): AlertType
    {
        return $this->type;
    }

    public function setType(AlertType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSeverity(): AlertSeverity
    {
        return $this->severity;
    }

    public function setSeverity(AlertSeverity $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function getStatus(): AlertStatus
    {
        return $this->status;
    }

    public function setStatus(AlertStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAffectedEntityType(): AlertEntityType
    {
        return $this->affectedEntityType;
    }

    public function setAffectedEntityType(AlertEntityType $affectedEntityType): self
    {
        $this->affectedEntityType = $affectedEntityType;

        return $this;
    }

    public function getRecommendedAction(): string
    {
        return $this->recommendedAction;
    }

    public function setRecommendedAction(string $recommendedAction): self
    {
        $this->recommendedAction = $recommendedAction;

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

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): self
    {
        $this->acknowledgedAt = $acknowledgedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getSystem(): ?System
    {
        return $this->system;
    }

    public function setSystem(?System $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getBatch(): ?ZebrafishBatch
    {
        return $this->batch;
    }

    public function setBatch(?ZebrafishBatch $batch): self
    {
        $this->batch = $batch;

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
