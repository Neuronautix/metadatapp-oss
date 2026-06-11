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
class CurationDecision
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['decision.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ImportSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportSession $session;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['decision.read'])]
    private string $proposalType; // schema_mapping, value_normalization, ontology_term, patch

    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['decision.read'])]
    private Uuid $proposalId;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['decision.read'])]
    private string $decision; // approved, rejected, overridden

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['decision.read'])]
    private \DateTimeImmutable $decidedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)] // null if sys/auto
    #[Groups(['decision.read'])]
    private ?User $decidedBy = null;

    public function __construct()
    {
        $this->decidedAt = new \DateTimeImmutable();
    }

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

    public function getProposalType(): string
    {
        return $this->proposalType;
    }

    public function setProposalType(string $proposalType): self
    {
        $this->proposalType = $proposalType;

        return $this;
    }

    public function getProposalId(): Uuid
    {
        return $this->proposalId;
    }

    public function setProposalId(Uuid $proposalId): self
    {
        $this->proposalId = $proposalId;

        return $this;
    }

    public function getDecision(): string
    {
        return $this->decision;
    }

    public function setDecision(string $decision): self
    {
        $this->decision = $decision;

        return $this;
    }

    public function getDecidedAt(): \DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(\DateTimeImmutable $decidedAt): self
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function setDecidedBy(?User $decidedBy): self
    {
        $this->decidedBy = $decidedBy;

        return $this;
    }
}
