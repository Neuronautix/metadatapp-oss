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
class ImportRow
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['import_row.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ImportSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportSession $session;

    #[ORM\Column(type: 'integer')]
    #[Groups(['import_row.read'])]
    private int $rowIndex;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['import_row.read'])]
    private string $rowHash;

    #[ORM\Column(type: 'json')]
    #[Groups(['import_row.read'])]
    private array $sourcePayload = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['import_row.read'])]
    private ?array $normalizedPayload = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['import_row.read'])]
    private string $resolutionStatus = 'unresolved';

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    #[Groups(['import_row.read'])]
    private ?Uuid $resolvedSubjectId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['import_row.read'])]
    private ?array $rowErrorCodes = null;

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

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function setRowIndex(int $rowIndex): self
    {
        $this->rowIndex = $rowIndex;

        return $this;
    }

    public function getRowHash(): string
    {
        return $this->rowHash;
    }

    public function setRowHash(string $rowHash): self
    {
        $this->rowHash = $rowHash;

        return $this;
    }

    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    public function setSourcePayload(array $sourcePayload): self
    {
        $this->sourcePayload = $sourcePayload;

        return $this;
    }

    public function getNormalizedPayload(): ?array
    {
        return $this->normalizedPayload;
    }

    public function setNormalizedPayload(?array $normalizedPayload): self
    {
        $this->normalizedPayload = $normalizedPayload;

        return $this;
    }

    public function getResolutionStatus(): string
    {
        return $this->resolutionStatus;
    }

    public function setResolutionStatus(string $resolutionStatus): self
    {
        $this->resolutionStatus = $resolutionStatus;

        return $this;
    }

    public function getResolvedSubjectId(): ?Uuid
    {
        return $this->resolvedSubjectId;
    }

    public function setResolvedSubjectId(?Uuid $resolvedSubjectId): self
    {
        $this->resolvedSubjectId = $resolvedSubjectId;

        return $this;
    }

    public function getRowErrorCodes(): ?array
    {
        return $this->rowErrorCodes;
    }

    public function setRowErrorCodes(?array $rowErrorCodes): self
    {
        $this->rowErrorCodes = $rowErrorCodes;

        return $this;
    }
}
