<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GuidelineReviewEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Append-only audit log for the guideline QC/review workflow, tenant-scoped.
 *
 * One row per field-level review toggle, per value edit that invalidates a
 * prior review, and per report-level sign-off decision. Never updated or
 * deleted — the current report-level status is derived by reading the latest
 * `report_*` event for a (resourceType, resourceId, templateId), so there is
 * no separate mutable "current state" row to keep in sync. Field-level current
 * state is denormalized on `GuidelineFieldValue` for cheap reads; this table
 * is the history behind it.
 *
 * No #[ApiResource]: written/read only through GuidelineController, mirroring
 * GuidelineFieldValue.
 */
#[ORM\Entity(repositoryClass: GuidelineReviewEventRepository::class)]
#[ORM\Table(name: 'guideline_review_event')]
#[ORM\Index(name: 'idx_guideline_review_event_lookup', columns: ['resource_type', 'resource_id', 'template_id'])]
class GuidelineReviewEvent implements AccountAwareInterface
{
    public const string ACTION_FIELD_REVIEWED = 'field_reviewed';
    public const string ACTION_FIELD_UNREVIEWED = 'field_unreviewed';
    public const string ACTION_FIELD_VALUE_CHANGED = 'field_value_changed';
    public const string ACTION_REPORT_APPROVED = 'report_approved';
    public const string ACTION_REPORT_CHANGES_REQUESTED = 'report_changes_requested';

    /** Actions that settle the report-level status (the ones a status lookup scans for). */
    public const array REPORT_ACTIONS = [self::ACTION_REPORT_APPROVED, self::ACTION_REPORT_CHANGES_REQUESTED];

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

    /** Null for report-level events. */
    #[ORM\Column(name: 'field_id', type: 'string', length: 128, nullable: true)]
    private ?string $fieldId = null;

    #[ORM\Column(name: 'action', type: 'string', length: 32)]
    private string $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', nullable: false)]
    private User $actor;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'note', type: 'string', length: 1000, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
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

    public function getFieldId(): ?string
    {
        return $this->fieldId;
    }

    public function setFieldId(?string $fieldId): self
    {
        $this->fieldId = $fieldId;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function setActor(User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = null !== $note ? mb_substr($note, 0, 1000) : null;

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
