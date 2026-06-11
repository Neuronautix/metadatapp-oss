<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\SystemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SystemRepository::class)]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['system.read', 'read:nested']],
    denormalizationContext: ['groups' => ['system.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'ipartial', 'manufacturer' => 'ipartial', 'model' => 'ipartial', 'room' => 'exact', 'room.id' => 'exact', 'racks' => 'exact', 'racks.id' => 'exact', 'racks.room.id' => 'exact'])]
class System implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['system.read', 'read:nested'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Groups(['system.read', 'system.write', 'read:nested'])]
    private string $code;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['system.read', 'system.write'])]
    private string $manufacturer;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['system.read', 'system.write'])]
    private string $model;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'systems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['system.read', 'system.write', 'read:nested'])]
    private Room $room;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['system.read', 'system.write'])]
    private ?float $waterTemperature = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['system.read', 'system.write'])]
    private ?float $pH = null;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['system.read', 'system.write'])]
    private float $conductivity = 0.0;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['system.read', 'system.write'])]
    private float $oxygen = 0.0;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['system.read', 'system.write'])]
    private float $waterFlow = 0.0;

    #[ORM\Column(length: 16)]
    #[Assert\Choice(['OK', 'MAINTENANCE', 'FAILURE'])]
    #[Groups(['system.read', 'system.write'])]
    private string $filtrationStatus = 'OK';

    /**
     * @var Collection<int, EnvironmentalObservation>
     */
    #[ORM\OneToMany(mappedBy: 'system', targetEntity: EnvironmentalObservation::class)]
    private Collection $observations;

    /**
     * @var Collection<int, Rack>
     */
    #[ORM\OneToMany(mappedBy: 'system', targetEntity: Rack::class)]
    private Collection $racks;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function __construct()
    {
        $this->observations = new ArrayCollection();
        $this->racks = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getRoom(): Room
    {
        return $this->room;
    }

    public function setRoom(Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    /**
     * @return Collection<int, EnvironmentalObservation>
     */
    public function getObservations(): Collection
    {
        return $this->observations;
    }

    /**
     * @return Collection<int, Rack>
     */
    public function getRacks(): Collection
    {
        return $this->racks;
    }

    public function getWaterTemperature(): ?float
    {
        return $this->waterTemperature;
    }

    public function setWaterTemperature(?float $waterTemperature): self
    {
        $this->waterTemperature = $waterTemperature;

        return $this;
    }

    public function getPH(): ?float
    {
        return $this->pH;
    }

    public function setPH(?float $pH): self
    {
        $this->pH = $pH;

        return $this;
    }

    public function getConductivity(): float
    {
        return $this->conductivity;
    }

    public function setConductivity(float $conductivity): self
    {
        $this->conductivity = $conductivity;

        return $this;
    }

    public function getOxygen(): float
    {
        return $this->oxygen;
    }

    public function setOxygen(float $oxygen): self
    {
        $this->oxygen = $oxygen;

        return $this;
    }

    public function getWaterFlow(): float
    {
        return $this->waterFlow;
    }

    public function setWaterFlow(float $waterFlow): self
    {
        $this->waterFlow = $waterFlow;

        return $this;
    }

    public function getFiltrationStatus(): string
    {
        return $this->filtrationStatus;
    }

    public function setFiltrationStatus(string $filtrationStatus): self
    {
        $this->filtrationStatus = $filtrationStatus;

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
