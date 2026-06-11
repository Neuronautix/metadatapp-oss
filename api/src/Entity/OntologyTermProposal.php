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
class OntologyTermProposal
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

    #[ORM\ManyToOne(targetEntity: ImportColumn::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ImportColumn $column = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['proposal.read'])]
    private string $targetField;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['proposal.read'])]
    private string $originalValue;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['proposal.read'])]
    private string $termId;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['proposal.read'])]
    private string $termLabel;

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

    public function getColumn(): ?ImportColumn
    {
        return $this->column;
    }

    public function setColumn(?ImportColumn $column): self
    {
        $this->column = $column;

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

    public function getOriginalValue(): string
    {
        return $this->originalValue;
    }

    public function setOriginalValue(string $originalValue): self
    {
        $this->originalValue = $originalValue;

        return $this;
    }

    public function getTermId(): string
    {
        return $this->termId;
    }

    public function setTermId(string $termId): self
    {
        $this->termId = $termId;

        return $this;
    }

    public function getTermLabel(): string
    {
        return $this->termLabel;
    }

    public function setTermLabel(string $termLabel): self
    {
        $this->termLabel = $termLabel;

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
