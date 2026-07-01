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
use App\Enum\ProcedureType;
use App\Enum\TreatmentCategory;
use App\Enum\TreatmentRoute;
use App\Enum\TreatmentType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a Treatment in a Procedure.
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
        AbstractNormalizer::GROUPS => ['treatment:read', 'query', 'query_collection'],
    ]
    ),
    denormalizationContext: ([
        AbstractNormalizer::GROUPS => ['treatment:write'],
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
)] class Treatment extends Procedure
{
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

    /**
     * Treatment specific properties.
     */
    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    #[ORM\Column(type: 'string', nullable: true, enumType: TreatmentType::class)]
    private ?TreatmentType $treatmentType = null;

    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\Column(type: 'string', nullable: true, enumType: TreatmentCategory::class)]
    private ?TreatmentCategory $category = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    private ?string $drug = null;

    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    private ?float $dose = null;

    #[ApiProperty(types: 'https://schema.org/doseUnit')]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    private ?string $doseUnit = null;

    #[Groups(['treatment:read', 'query', 'query_collection', 'treatment:write', 'read:nested', 'enum:read'])]
    #[ApiProperty(readableLink: true)]
    #[ORM\Column(name: 'route', type: 'string', nullable: true, enumType: TreatmentRoute::class)]
    private ?TreatmentRoute $route = null;

    public function getTreatmentType(): ?TreatmentType
    {
        return $this->treatmentType;
    }

    public function setTreatmentType(?TreatmentType $treatmentType): self
    {
        $this->treatmentType = $treatmentType;

        return $this;
    }

    public function getCategory(): ?TreatmentCategory
    {
        return $this->category;
    }

    public function setCategory(?TreatmentCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDrug(): ?string
    {
        return $this->drug;
    }

    public function setDrug(?string $drug): self
    {
        $this->drug = $drug;

        return $this;
    }

    public function getDose(): ?float
    {
        return $this->dose;
    }

    public function setDose(?float $dose): self
    {
        $this->dose = $dose;

        return $this;
    }

    public function getDoseUnit(): ?string
    {
        return $this->doseUnit;
    }

    public function setDoseUnit(?string $doseUnit): self
    {
        $this->doseUnit = $doseUnit;

        return $this;
    }

    public function getRoute(): ?TreatmentRoute
    {
        return $this->route;
    }

    public function setRoute(?TreatmentRoute $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getProcedureType(): ProcedureType
    {
        if (null === $this->procedureTypeEnum) {
            $this->procedureTypeEnum = ProcedureType::TREATMENT;
        }

        return $this->procedureTypeEnum;
    }
}
