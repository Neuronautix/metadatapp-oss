<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A single permissible-value level mapping inside a FieldCrosswalk
 * (source value -> target value/code/ontology term).
 */
#[ORM\Entity]
#[ORM\Table(name: 'crosswalk_value_crosswalk')]
class ValueCrosswalk
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['value_crosswalk.read', 'field_crosswalk.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: FieldCrosswalk::class, inversedBy: 'valueCrosswalks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FieldCrosswalk $fieldCrosswalk;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private ?string $sourceValue = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private ?string $targetValue = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private ?string $targetCode = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private ?string $ontologyTerm = null;

    #[ORM\Column(type: 'string', enumType: CrosswalkMappingType::class)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private CrosswalkMappingType $mappingType = CrosswalkMappingType::RELATED;

    #[ORM\Column(type: 'string', enumType: MappingStatus::class)]
    #[Groups(['value_crosswalk.read', 'value_crosswalk.write', 'field_crosswalk.read'])]
    private MappingStatus $mappingStatus = MappingStatus::SUGGESTED;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFieldCrosswalk(): FieldCrosswalk
    {
        return $this->fieldCrosswalk;
    }

    public function setFieldCrosswalk(FieldCrosswalk $fieldCrosswalk): self
    {
        $this->fieldCrosswalk = $fieldCrosswalk;

        return $this;
    }

    public function getSourceValue(): ?string
    {
        return $this->sourceValue;
    }

    public function setSourceValue(?string $sourceValue): self
    {
        $this->sourceValue = $sourceValue;

        return $this;
    }

    public function getTargetValue(): ?string
    {
        return $this->targetValue;
    }

    public function setTargetValue(?string $targetValue): self
    {
        $this->targetValue = $targetValue;

        return $this;
    }

    public function getTargetCode(): ?string
    {
        return $this->targetCode;
    }

    public function setTargetCode(?string $targetCode): self
    {
        $this->targetCode = $targetCode;

        return $this;
    }

    public function getOntologyTerm(): ?string
    {
        return $this->ontologyTerm;
    }

    public function setOntologyTerm(?string $ontologyTerm): self
    {
        $this->ontologyTerm = $ontologyTerm;

        return $this;
    }

    public function getMappingType(): CrosswalkMappingType
    {
        return $this->mappingType;
    }

    public function setMappingType(CrosswalkMappingType $mappingType): self
    {
        $this->mappingType = $mappingType;

        return $this;
    }

    public function getMappingStatus(): MappingStatus
    {
        return $this->mappingStatus;
    }

    public function setMappingStatus(MappingStatus $mappingStatus): self
    {
        $this->mappingStatus = $mappingStatus;

        return $this;
    }
}
