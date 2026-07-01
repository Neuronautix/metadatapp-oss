<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\EnvironmentalMetric;
use App\Enum\EnvironmentalObservationSource;
use App\State\Processor\EnvironmentalObservationPersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\EnvironmentalObservationRepository::class)]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['environmental_observation.read', 'read:nested'], AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
    denormalizationContext: ['groups' => ['environmental_observation.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            processor: EnvironmentalObservationPersistProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        'system' => 'exact',
        'system.id' => 'exact',
        'room' => 'exact',
        'room.id' => 'exact',
        'metric' => 'exact',
        'source' => 'exact',
    ]
)]
#[ApiFilter(DateFilter::class, properties: ['timestamp'])]
class EnvironmentalObservation implements AccountAwareInterface, UserAwareInterface
{
    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['environmental_observation.read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: System::class, inversedBy: 'observations')]
    #[Groups(['environmental_observation.read', 'environmental_observation.write', 'read:nested'])]
    #[MaxDepth(1)]
    private ?System $system = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[Groups(['environmental_observation.read', 'environmental_observation.write', 'read:nested'])]
    #[MaxDepth(1)]
    private ?Room $room = null;

    #[ORM\Column(type: 'string', enumType: EnvironmentalMetric::class)]
    #[Assert\NotNull]
    #[Groups(['environmental_observation.read', 'environmental_observation.write', 'enum:read'])]
    private EnvironmentalMetric $metric;

    #[ORM\Column(type: 'float')]
    #[Assert\NotNull]
    #[Groups(['environmental_observation.read', 'environmental_observation.write'])]
    private float $value;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    #[Groups(['environmental_observation.read', 'environmental_observation.write'])]
    private \DateTimeImmutable $timestamp;

    #[ORM\Column(type: 'string', enumType: EnvironmentalObservationSource::class)]
    #[Assert\NotNull]
    #[Groups(['environmental_observation.read', 'environmental_observation.write', 'enum:read'])]
    private EnvironmentalObservationSource $source = EnvironmentalObservationSource::MANUAL;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['environmental_observation.read', 'environmental_observation.write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false)]
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

    public function getSystem(): ?System
    {
        return $this->system;
    }

    public function setSystem(?System $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getMetric(): EnvironmentalMetric
    {
        return $this->metric;
    }

    public function setMetric(EnvironmentalMetric $metric): self
    {
        $this->metric = $metric;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getSource(): EnvironmentalObservationSource
    {
        return $this->source;
    }

    public function setSource(EnvironmentalObservationSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getRecorder(): string
    {
        return $this->user->getName() ?? $this->user->getEmail();
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
