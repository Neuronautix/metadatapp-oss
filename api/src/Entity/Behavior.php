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
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a Behavior Test in a Procedure.
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
        AbstractNormalizer::GROUPS => ['behavior:read', 'query', 'query_collection'],
    ]
    ),
    denormalizationContext: ([
        AbstractNormalizer::GROUPS => ['behavior:write', 'procedure:write'],
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
class Behavior extends Procedure
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

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['behavior:read', 'query', 'query_collection', 'behavior:write', 'read:nested', 'enum:read'])]
    private ?string $apparatus = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['behavior:read', 'query', 'query_collection', 'behavior:write', 'read:nested', 'enum:read'])]
    private ?string $context = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['behavior:read', 'query', 'query_collection', 'behavior:write', 'read:nested', 'enum:read'])]
    private ?float $lighting = null;

    public function getApparatus(): ?string
    {
        return $this->apparatus;
    }

    public function setApparatus(?string $apparatus): self
    {
        $this->apparatus = $apparatus;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getLighting(): ?float
    {
        return $this->lighting;
    }

    public function setLighting(?float $lighting): self
    {
        $this->lighting = $lighting;

        return $this;
    }

    #[ApiProperty(readableLink: true)]
    public function getProcedureType(): ProcedureType
    {
        if (null === $this->procedureTypeEnum) {
            $this->procedureTypeEnum = ProcedureType::BEHAVIOR;
        }

        return $this->procedureTypeEnum;
    }
}
