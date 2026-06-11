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

/**
 * Represents a Home Cage Monitoring system.
 */
#[ORM\Entity]
#[ApiResource]
class HomeCageMonitoring implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Experiment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Experiment $experiment;

    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $system;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    /**
     * Hour (0-23, UTC) when lights turn on in the holding room.
     * Used for Zeitgeber-time computation in circadian analysis.
     * Null means the light schedule has not been recorded.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $lightsOnUtcHour = null;

    /**
     * Duration of the light (active) phase in hours (1-24).
     * Defaults to 12 h (standard 12:12 LD cycle).
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $lightDurationHours = 12;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getExperiment(): Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(Experiment $experiment): self
    {
        $this->experiment = $experiment;

        return $this;
    }

    public function getSystem(): string
    {
        return $this->system;
    }

    public function setSystem(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    #[ApiProperty(iris: ['https://schema.org/name'])]
    public function getName(): string
    {
        return $this->system . ' - ' . substr((string) $this->getId(), 0, 8);
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

    public function getLightsOnUtcHour(): ?int
    {
        return $this->lightsOnUtcHour;
    }

    public function setLightsOnUtcHour(?int $lightsOnUtcHour): self
    {
        $this->lightsOnUtcHour = $lightsOnUtcHour;

        return $this;
    }

    public function getLightDurationHours(): ?int
    {
        return $this->lightDurationHours;
    }

    public function setLightDurationHours(?int $lightDurationHours): self
    {
        $this->lightDurationHours = $lightDurationHours;

        return $this;
    }
}
