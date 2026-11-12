<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Enum\LightCycle;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Represents the environment housing conditions.
 */
#[ORM\Entity]
#[ApiResource]
class EnvironmentHousing implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[Groups(['read:nested'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    private ?Uuid $id = null;

    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $room;

    /**
     * Temperature in the room. The unit is °C.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $temperature = null;  // https://www.ontologyrepository.com/CommonCoreOntologies/Temperature ? and http://www.ontology-of-units-of-measure.org/resource/om-2/degreeCelsius

    /**
     * Humidity in the air. The unit is %.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $humidity = null;

    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $luminosity = null;

    /**
     * Ventilation in the room. The unit is bar or hPa.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $pressure = null;

    /**
     * Noise in the room. The unit is dB.
     */
    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $noise = null;

    #[Groups(['read:nested'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: LightCycle::class)]
    private ?LightCycle $lightCycle = null;

    #[Groups(['read:nested'])]
    #[ORM\ManyToOne(targetEntity: AnimalFacility::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AnimalFacility $animalFacility;

    #[ORM\ManyToOne(targetEntity: Experiment::class)]
    private ?Experiment $experiment = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getRoom(): string
    {
        return $this->room;
    }

    public function setRoom(string $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getHumidity(): ?float
    {
        return $this->humidity;
    }

    public function setHumidity(?float $humidity): self
    {
        $this->humidity = $humidity;

        return $this;
    }

    public function getLuminosity(): ?float
    {
        return $this->luminosity;
    }

    public function setLuminosity(?float $luminosity): self
    {
        $this->luminosity = $luminosity;

        return $this;
    }

    public function getPressure(): ?float
    {
        return $this->pressure;
    }

    public function setPressure(?float $pressure): self
    {
        $this->pressure = $pressure;

        return $this;
    }

    public function getNoise(): ?float
    {
        return $this->noise;
    }

    public function setNoise(?float $noise): self
    {
        $this->noise = $noise;

        return $this;
    }

    public function getLightCycle(): ?LightCycle
    {
        return $this->lightCycle;
    }

    public function setLightCycle(?LightCycle $lightCycle): self
    {
        $this->lightCycle = $lightCycle;

        return $this;
    }

    public function getAnimalFacility(): AnimalFacility
    {
        return $this->animalFacility;
    }

    public function setAnimalFacility(AnimalFacility $animalFacility): self
    {
        $this->animalFacility = $animalFacility;

        return $this;
    }

    public function getExperiment(): ?Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(?Experiment $experiment): self
    {
        $this->experiment = $experiment;

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
}
