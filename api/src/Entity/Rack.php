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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['rack.read', 'read:nested']],
    denormalizationContext: ['groups' => ['rack.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'ipartial', 'room' => 'exact', 'room.id' => 'exact', 'room.plateau' => 'exact', 'room.plateau.id' => 'exact', 'system' => 'exact', 'system.id' => 'exact', 'system.code' => 'exact'])]
class Rack implements AccountAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['rack.read', 'read:nested'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Groups(['rack.read', 'rack.write', 'read:nested'])]
    private string $code;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'racks')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['rack.read', 'rack.write', 'read:nested'])]
    private Room $room;

    #[ORM\ManyToOne(targetEntity: System::class, inversedBy: 'racks')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['rack.read', 'rack.write', 'read:nested'])]
    private ?System $system = null;

    /**
     * @var Collection<int, Pool>
     */
    #[ORM\OneToMany(mappedBy: 'rack', targetEntity: Pool::class)]
    private Collection $pools;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
    private Account $account;

    public function __construct()
    {
        $this->pools = new ArrayCollection();
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

    public function getRoom(): Room
    {
        return $this->room;
    }

    public function setRoom(Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getSystem(): ?System
    {
        return $this->system;
    }

    public function setSystem(?System $system): self
    {
        $this->system = $system;

        return $this;
    }

    /**
     * @return Collection<int, Pool>
     */
    public function getPools(): Collection
    {
        return $this->pools;
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
