<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GuidelineFieldValueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A persisted fill value for a single guideline field (§7), tenant-scoped.
 *
 * Stored per (resourceType, resourceId, templateId, fieldId). `source`
 * records the fill channel (manual | ai | paper). This is the durable backing
 * for the shared metadata dict overlay built by ResourceMetadataResolver.
 *
 * No #[ApiResource]: written/read only through the guideline State providers
 * and processors, so tenant isolation stays in those handlers.
 */
#[ORM\Entity(repositoryClass: GuidelineFieldValueRepository::class)]
#[ORM\Table(name: 'guideline_field_value')]
#[ORM\UniqueConstraint(
    name: 'uniq_guideline_field_value',
    columns: ['resource_type', 'resource_id', 'template_id', 'field_id'],
)]
#[ORM\Index(name: 'idx_guideline_field_value_lookup', columns: ['resource_type', 'resource_id', 'template_id'])]
class GuidelineFieldValue implements AccountAwareInterface, UserAwareInterface
{
    public const string SOURCE_MANUAL = 'manual';
    public const string SOURCE_AI = 'ai';
    public const string SOURCE_PAPER = 'paper';

    public const string RESOURCE_STUDY = 'study';
    public const string RESOURCE_INVESTIGATION = 'investigation';

    public const string REVIEW_DRAFT = 'draft';
    public const string REVIEW_REVIEWED = 'reviewed';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(name: 'resource_type', type: 'string', length: 32)]
    private string $resourceType;

    #[ORM\Column(name: 'resource_id', type: UuidType::NAME)]
    private Uuid $resourceId;

    #[ORM\Column(name: 'template_id', type: 'string', length: 128)]
    private string $templateId;

    #[ORM\Column(name: 'field_id', type: 'string', length: 128)]
    private string $fieldId;

    /**
     * The filled value. Stored as JSON to support strings, arrays or scalars.
     */
    #[ORM\Column(name: 'value', type: 'json', nullable: true)]
    private mixed $value = null;

    #[ORM\Column(name: 'source', type: 'string', length: 16)]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Denormalized current QC state (§ review workflow): whether a human has
     * signed off on this value. Reset to `draft` whenever the value changes —
     * see `resetReview()`, called from `GuidelineController::upsert()`. The full
     * history of transitions lives in `GuidelineReviewEvent`, not here.
     */
    #[ORM\Column(name: 'review_status', type: 'string', length: 16)]
    private string $reviewStatus = self::REVIEW_DRAFT;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', nullable: true)]
    private ?User $reviewedBy = null;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getResourceId(): Uuid
    {
        return $this->resourceId;
    }

    public function setResourceId(Uuid $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getFieldId(): string
    {
        return $this->fieldId;
    }

    public function setFieldId(string $fieldId): self
    {
        $this->fieldId = $fieldId;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getReviewStatus(): string
    {
        return $this->reviewStatus;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function markReviewed(User $reviewer): self
    {
        $this->reviewStatus = self::REVIEW_REVIEWED;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Invalidates any prior sign-off. Called both when a reviewer explicitly
     * un-reviews a field and, from `GuidelineController::upsert()`, whenever the
     * value itself changes — an edited value needs a fresh review.
     */
    public function resetReview(): self
    {
        $this->reviewStatus = self::REVIEW_DRAFT;
        $this->reviewedBy = null;
        $this->reviewedAt = null;

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
