<?php

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
 * Represents body weight measurements for subjects.
 */
#[ORM\Entity]
#[ApiResource]
class WeightMeasurement implements AccountAwareInterface, UserAwareInterface
{
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    //    #[ApiProperty(readable: false)]
    private User $user;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(name: 'elaftw_external_id', type: 'string', nullable: true)]
    private ?string $elaftwExternalId = null;

    #[ORM\Column(name: 'fair3r_external_id', type: 'string', nullable: true)]
    private ?string $fair3rExternalId = null;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'weightMeasurements')]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull]
    #[Groups(['subject.read'])]
    private \DateTimeInterface $measuredAt;

    /*
     * Weight in kg like in this spec ???
     */
    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    #[ApiProperty(types: 'https://schema.org/weight')]
    #[Groups(['subject.read'])]
    private float $weight;

    /**    @todo add procedure as parentEntities */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMeasuredAt(): \DateTimeInterface
    {
        return $this->measuredAt;
    }

    public function setMeasuredAt(\DateTimeInterface $measuredAt): self
    {
        $this->measuredAt = $measuredAt;

        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = $weight;

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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getElaftwExternalId(): ?string
    {
        return $this->elaftwExternalId;
    }

    public function setElaftwExternalId(?string $elaftwExternalId): void
    {
        $this->elaftwExternalId = $elaftwExternalId;
    }

    public function getFair3rExternalId(): ?string
    {
        return $this->fair3rExternalId;
    }

    public function setFair3rExternalId(?string $fair3rExternalId): void
    {
        $this->fair3rExternalId = $fair3rExternalId;
    }
}
