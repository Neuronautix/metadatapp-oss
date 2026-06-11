<?php

// // lolololo T'EN ES LA À)   15h33
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a Strain of an animal.
 */
#[ORM\Entity]
#[ApiResource]
class Strain
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(types: 'https://schema.org/name', iris: ['https://schema.org/name'])]
    #[Assert\NotBlank]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ApiProperty(types: 'https://schema.org/url')]
    //    #[Assert\Url]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $link = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): self
    {
        $this->link = $link;

        return $this;
    }
}
