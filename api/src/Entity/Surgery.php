<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\InvasivenessType;
use App\Enum\ProcedureType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Represents a Surgery Procedure.
 * https://schema.org/MedicalProcedureType.
 */
#[ORM\Entity]
#[ApiResource(
    types: 'https://schema.org/MedicalProcedure',
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
    ],
    normalizationContext: ([
        AbstractNormalizer::GROUPS => ['surgery:read', 'procedure:read', 'query', 'query_collection'],
    ]
    ),
    denormalizationContext: ([
        AbstractNormalizer::GROUPS => ['surgery:write', 'procedure:write'],
    ]
    ),
    graphQlOperations: [
        new Query(
            normalizationContext: [
                AbstractNormalizer::GROUPS => ['query', 'enum:read'],
            ]
        ),
        new QueryCollection(normalizationContext: [
            AbstractNormalizer::GROUPS => ['query_collection', 'enum:read'],
        ]),
        new Mutation(
            normalizationContext: [AbstractNormalizer::GROUPS => ['query_collection', 'enum:read']],
            denormalizationContext: [AbstractNormalizer::GROUPS => ['mutation']],
            name: 'create'
        ),
        new QueryCollection(normalizationContext: [
            AbstractNormalizer::GROUPS => ['query_collection', 'enum:read'],
        ]),
        new Query(
            normalizationContext: [AbstractNormalizer::GROUPS => ['query', 'enum:read']],
        ),
    ]
)]
class Surgery extends Procedure
{
    // btmp user and account here not in abstract class to solve nullable and migration issues
    #[Groups(['procedure:read', 'query', 'query_collection', 'procedure:write'])]
    //    #[ORM\ManyToOne(targetEntity: User::class)]
    //    private User $user;
    //
    //    #[ORM\ManyToOne(targetEntity: Account::class)]
    //    #[ApiProperty(readable: false)]
    //    private Account $account;

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

    // use TimestampableEntity;
    // use BlameableEntity;
    // use SoftDeleteableEntity;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $surgeryName = null;

    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\Column(type: 'string', nullable: true, enumType: InvasivenessType::class)]
    private ?InvasivenessType $invasivenessType = null;

    /**
     * Details about the invasiveness,
     * Incisions (type of incision, size, etc.), Biopsy (type of biopsy, size, etc.), Surgery (type of surgery, size, etc.)*.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $invasivenessDetails = null;

    /**
     * The actor ohtyhe surgery, the surgerer.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $actor = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?bool $hasAnesthesia = null;

    /**
     * Details about the anesthesia used, name of the drug, dosage etc.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $anesthesiaDetails = null;

    /**
     * Duration of the surgery in seconds.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?int $surgeryDuration = null;

    /**
     * Duration of the recovery in seconds.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?int $recoveryDuration = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $recoveryDetails = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['surgery:read', 'procedure:read', 'query', 'query_collection', 'surgery:write', 'read:nested', 'enum:read'])]
    private ?string $complications = null;

    public function getSurgeryName(): ?string
    {
        return $this->surgeryName;
    }

    public function setSurgeryName(?string $surgeryName): self
    {
        $this->surgeryName = $surgeryName;

        return $this;
    }

    public function getInvasivenessType(): ?InvasivenessType
    {
        return $this->invasivenessType;
    }

    public function setInvasivenessType(?InvasivenessType $invasivenessType): self
    {
        $this->invasivenessType = $invasivenessType;

        return $this;
    }

    public function getInvasivenessDetails(): ?string
    {
        return $this->invasivenessDetails;
    }

    public function setInvasivenessDetails(?string $invasivenessDetails): self
    {
        $this->invasivenessDetails = $invasivenessDetails;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getHasAnesthesia(): ?bool
    {
        return $this->hasAnesthesia;
    }

    public function setHasAnesthesia(?bool $hasAnesthesia): self
    {
        $this->hasAnesthesia = $hasAnesthesia;

        return $this;
    }

    public function getAnesthesiaDetails(): ?string
    {
        return $this->anesthesiaDetails;
    }

    public function setAnesthesiaDetails(?string $anesthesiaDetails): self
    {
        $this->anesthesiaDetails = $anesthesiaDetails;

        return $this;
    }

    public function getSurgeryDuration(): ?int
    {
        return $this->surgeryDuration;
    }

    public function setSurgeryDuration(?int $surgeryDuration): self
    {
        $this->surgeryDuration = $surgeryDuration;

        return $this;
    }

    public function getRecoveryDuration(): ?int
    {
        return $this->recoveryDuration;
    }

    public function setRecoveryDuration(?int $recoveryDuration): self
    {
        $this->recoveryDuration = $recoveryDuration;

        return $this;
    }

    public function getRecoveryDetails(): ?string
    {
        return $this->recoveryDetails;
    }

    public function setRecoveryDetails(?string $recoveryDetails): self
    {
        $this->recoveryDetails = $recoveryDetails;

        return $this;
    }

    public function getComplications(): ?string
    {
        return $this->complications;
    }

    public function setComplications(?string $complications): self
    {
        $this->complications = $complications;

        return $this;
    }

    public function getProcedureType(): ProcedureType
    {
        if (null === $this->procedureTypeEnum) {
            $this->procedureTypeEnum = ProcedureType::SURGERY;
        }

        return $this->procedureTypeEnum;
    }
}
