<?php

declare(strict_types=1);

namespace App\Entity\Impc;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ApiResource]
class ImpcGene
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator')]
    private ?Uuid $id = null;
    #[ORM\Column(type: 'string', length: 255)]
    private string $mgiId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $symbol;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * Keeps Doctrine-generated UUID assignment visible to static analysis.
     */
    public function assignId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getMgiId(): string
    {
        return $this->mgiId;
    }

    public function setMgiId(string $mgiId): self
    {
        $this->mgiId = $mgiId;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
