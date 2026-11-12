<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Stores sinusoidal model fit parameters derived from HCM data for a cage on a given day.
 */
#[ORM\Entity]
#[ApiResource]
class CageHcmSinusoidMetadata implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[ApiProperty(readable: false)]
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Cage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Cage $cage;

    #[ORM\ManyToOne(targetEntity: HomeCageMonitoring::class)]
    #[ORM\JoinColumn(nullable: false)]
    private HomeCageMonitoring $homeCageMonitoring;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    /** Peak-to-trough half-amplitude of the fitted sine wave. */
    #[ORM\Column(type: 'float')]
    private float $amplitude;

    /** Midline estimating statistic of rhythm (mean value / baseline). */
    #[ORM\Column(type: 'float')]
    private float $mesor;

    /** Phase angle in radians (typically -π to +π). */
    #[ORM\Column(type: 'float')]
    private float $phase;

    /** Estimated peak activity hour (0.0–23.9). */
    #[ORM\Column(type: 'float')]
    private float $peakHour;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $sourceTaskId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $sourceAppCode = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

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

    public function getCage(): Cage
    {
        return $this->cage;
    }

    public function setCage(Cage $cage): self
    {
        $this->cage = $cage;

        return $this;
    }

    public function getHomeCageMonitoring(): HomeCageMonitoring
    {
        return $this->homeCageMonitoring;
    }

    public function setHomeCageMonitoring(HomeCageMonitoring $homeCageMonitoring): self
    {
        $this->homeCageMonitoring = $homeCageMonitoring;

        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getAmplitude(): float
    {
        return $this->amplitude;
    }

    public function setAmplitude(float $amplitude): self
    {
        $this->amplitude = $amplitude;

        return $this;
    }

    public function getMesor(): float
    {
        return $this->mesor;
    }

    public function setMesor(float $mesor): self
    {
        $this->mesor = $mesor;

        return $this;
    }

    public function getPhase(): float
    {
        return $this->phase;
    }

    public function setPhase(float $phase): self
    {
        $this->phase = $phase;

        return $this;
    }

    public function getPeakHour(): float
    {
        return $this->peakHour;
    }

    public function setPeakHour(float $peakHour): self
    {
        $this->peakHour = $peakHour;

        return $this;
    }

    public function getSourceTaskId(): ?string
    {
        return $this->sourceTaskId;
    }

    public function setSourceTaskId(?string $sourceTaskId): self
    {
        $this->sourceTaskId = $sourceTaskId;

        return $this;
    }

    public function getSourceAppCode(): ?string
    {
        return $this->sourceAppCode;
    }

    public function setSourceAppCode(?string $sourceAppCode): self
    {
        $this->sourceAppCode = $sourceAppCode;

        return $this;
    }
}
