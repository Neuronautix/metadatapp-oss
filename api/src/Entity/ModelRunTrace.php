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
class ModelRunTrace
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['trace.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ImportSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportSession $session;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['trace.read'])]
    private \DateTimeImmutable $runAt;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['trace.read'])]
    private string $step;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['trace.read'])]
    private string $modelInfo;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['trace.read'])]
    private ?array $promptPayload = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['trace.read'])]
    private ?array $responsePayload = null;

    public function __construct()
    {
        $this->runAt = new \DateTimeImmutable();
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

    public function getRunAt(): \DateTimeImmutable
    {
        return $this->runAt;
    }

    public function setRunAt(\DateTimeImmutable $runAt): self
    {
        $this->runAt = $runAt;

        return $this;
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function setStep(string $step): self
    {
        $this->step = $step;

        return $this;
    }

    public function getModelInfo(): string
    {
        return $this->modelInfo;
    }

    public function setModelInfo(string $modelInfo): self
    {
        $this->modelInfo = $modelInfo;

        return $this;
    }

    public function getPromptPayload(): ?array
    {
        return $this->promptPayload;
    }

    public function setPromptPayload(?array $promptPayload): self
    {
        $this->promptPayload = $promptPayload;

        return $this;
    }

    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(?array $responsePayload): self
    {
        $this->responsePayload = $responsePayload;

        return $this;
    }
}
