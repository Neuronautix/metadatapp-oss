<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CurationSuggestionStatus;
use App\Enum\CurationTaskType;
use App\Repository\CurationSuggestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CurationSuggestionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CurationSuggestion
{
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Subject $subject;

    #[ORM\Column(type: 'string', length: 100)]
    private string $resourceType = 'subject';

    #[ORM\Column(type: 'string', length: 36)]
    private string $resourceId = '';

    #[ORM\Column(type: 'string', enumType: CurationTaskType::class)]
    private CurationTaskType $taskType;

    #[ORM\Column(type: 'string', enumType: CurationSuggestionStatus::class)]
    private CurationSuggestionStatus $status = CurationSuggestionStatus::PendingReview;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fieldName;

    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $currentValue = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private mixed $proposedValue = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $ontologyId = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $warningCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawLlmResponse = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $providerName = 'manual';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $providerMetadata = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $failureDetails = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): self
    {
        $this->subject = $subject;
        $this->resourceType = 'subject';
        $this->resourceId = (string) $subject->getId();

        return $this;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getTaskType(): CurationTaskType
    {
        return $this->taskType;
    }

    public function setTaskType(CurationTaskType $taskType): self
    {
        $this->taskType = $taskType;

        return $this;
    }

    public function getStatus(): CurationSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(CurationSuggestionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getCurrentValue(): mixed
    {
        return $this->currentValue;
    }

    public function setCurrentValue(mixed $currentValue): self
    {
        $this->currentValue = $currentValue;

        return $this;
    }

    public function getProposedValue(): mixed
    {
        return $this->proposedValue;
    }

    public function setProposedValue(mixed $proposedValue): self
    {
        $this->proposedValue = $proposedValue;

        return $this;
    }

    public function getOntologyId(): ?string
    {
        return $this->ontologyId;
    }

    public function setOntologyId(?string $ontologyId): self
    {
        $this->ontologyId = $ontologyId;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getWarningCode(): ?string
    {
        return $this->warningCode;
    }

    public function setWarningCode(?string $warningCode): self
    {
        $this->warningCode = $warningCode;

        return $this;
    }

    public function getRawLlmResponse(): ?string
    {
        return $this->rawLlmResponse;
    }

    public function setRawLlmResponse(?string $rawLlmResponse): self
    {
        $this->rawLlmResponse = $rawLlmResponse;

        return $this;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): self
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getProviderMetadata(): ?array
    {
        return $this->providerMetadata;
    }

    public function setProviderMetadata(?array $providerMetadata): self
    {
        $this->providerMetadata = $providerMetadata;

        return $this;
    }

    public function getFailureDetails(): ?array
    {
        return $this->failureDetails;
    }

    public function setFailureDetails(?array $failureDetails): self
    {
        $this->failureDetails = $failureDetails;

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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
