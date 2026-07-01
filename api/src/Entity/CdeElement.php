<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CdeElementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CdeElementRepository::class)]
#[ORM\Table(name: 'cde_element')]
#[ORM\UniqueConstraint(name: 'uniq_cde_account_tiny_id', columns: ['account_id', 'tiny_id'])]
#[ApiResource(
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => ['cde_element.read']],
    operations: [
        new GetCollection(),
        new Get(),
        new Delete(),
    ],
)]
class CdeElement implements AccountAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['cde_element.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private Account $account;

    #[ORM\Column(name: 'tiny_id', type: 'string', length: 64)]
    #[Groups(['cde_element.read'])]
    private string $tinyId;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    #[Groups(['cde_element.read'])]
    private ?string $version = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['cde_element.read'])]
    private ?string $source = null;

    #[ORM\Column(type: 'string', length: 512)]
    #[Groups(['cde_element.read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cde_element.read'])]
    private ?string $definition = null;

    #[ORM\Column(name: 'permissible_values', type: 'json', nullable: true)]
    #[Groups(['cde_element.read'])]
    private ?array $permissibleValues = null;

    #[ORM\Column(name: 'raw_data', type: 'json', nullable: true)]
    #[Groups(['cde_element.read'])]
    private ?array $rawData = null;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    #[Groups(['cde_element.read'])]
    private \DateTimeImmutable $importedAt;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getTinyId(): string
    {
        return $this->tinyId;
    }

    public function setTinyId(string $tinyId): self
    {
        $this->tinyId = $tinyId;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

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

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?string $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getPermissibleValues(): ?array
    {
        return $this->permissibleValues;
    }

    /**
     * @param array<int, mixed>|null $permissibleValues
     */
    public function setPermissibleValues(?array $permissibleValues): self
    {
        $this->permissibleValues = $permissibleValues;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    /**
     * @param array<string, mixed>|null $rawData
     */
    public function setRawData(?array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;

        return $this;
    }
}
