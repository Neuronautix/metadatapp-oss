<?php

declare(strict_types=1);

namespace App\Entity\Impc;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ApiResource]
class ImpcPhenotypeStatement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator')]
    private ?Uuid $id = null;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: ImpcGene::class)]
    private ImpcGene $gene;

    #[ORM\Column(type: 'string', length: 255)]
    private string $phenotypeTerm;

    #[ORM\Column(type: 'float')]
    private float $pValue;

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

    public function getGene(): ImpcGene
    {
        return $this->gene;
    }

    public function setGene(ImpcGene $gene): self
    {
        $this->gene = $gene;

        return $this;
    }

    public function getPhenotypeTerm(): string
    {
        return $this->phenotypeTerm;
    }

    public function setPhenotypeTerm(string $phenotypeTerm): self
    {
        $this->phenotypeTerm = $phenotypeTerm;

        return $this;
    }

    public function getPValue(): float
    {
        return $this->pValue;
    }

    public function setPValue(float $pValue): self
    {
        $this->pValue = $pValue;

        return $this;
    }
}
