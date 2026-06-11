<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ApiResource]
class PatchProposal
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['proposal.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ImportSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportSession $session;

    #[ORM\ManyToOne(targetEntity: ImportRow::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportRow $row;

    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['proposal.read'])]
    private Uuid $subjectId;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['proposal.read'])]
    private string $targetField;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['proposal.read'])]
    private mixed $proposedValue = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['proposal.read'])]
    private string $decisionStatus = 'undecided';

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['proposal.read'])]
    private string $source = 'generated';

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSession(): ImportSession
    {
        return $this->session;
    }

    public function setSession(ImportSession $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function getRow(): ImportRow
    {
        return $this->row;
    }

    public function setRow(ImportRow $row): self
    {
        $this->row = $row;

        return $this;
    }

    public function getSubjectId(): Uuid
    {
        return $this->subjectId;
    }

    public function setSubjectId(Uuid $subjectId): self
    {
        $this->subjectId = $subjectId;

        return $this;
    }

    public function getTargetField(): string
    {
        return $this->targetField;
    }

    public function setTargetField(string $targetField): self
    {
        $this->targetField = $targetField;

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

    public function getDecisionStatus(): string
    {
        return $this->decisionStatus;
    }

    public function setDecisionStatus(string $decisionStatus): self
    {
        $this->decisionStatus = $decisionStatus;

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
}
