<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a measurable variable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'variable')]
#[ORM\UniqueConstraint(name: 'unique_name_account', columns: ['name', 'account_id'])]
#[ApiResource]
class Variable implements AccountAwareInterface, ConnectedAppEntityInterface
{
    #[ORM\Column(name: 'tecniplast_external_id', type: 'string', nullable: true)]
    private ?string $tecniplastExternalId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    #[ApiProperty(types: 'https://schema.org/name', iris: ['https://schema.org/name'])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $name;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getTecniplastExternalId(): ?string
    {
        return $this->tecniplastExternalId;
    }

    public function setTecniplastExternalId(?string $tecniplastExternalId): self
    {
        $this->tecniplastExternalId = $tecniplastExternalId;

        return $this;
    }
}
