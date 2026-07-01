<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A field-level mapping inside a TemplateFormCrosswalk: links one extracted
 * ELN field (and/or canonical form field) to one CDE.
 */
#[ORM\Entity]
#[ORM\Table(name: 'crosswalk_field_crosswalk')]
class FieldCrosswalk
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['field_crosswalk.read', 'template_form_crosswalk.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: TemplateFormCrosswalk::class, inversedBy: 'fieldCrosswalks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TemplateFormCrosswalk $templateFormCrosswalk;

    #[ORM\ManyToOne(targetEntity: CanonicalFormField::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?CanonicalFormField $canonicalFormField = null;

    #[ORM\ManyToOne(targetEntity: CommonDataElement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?CommonDataElement $cde = null;

    #[ORM\ManyToOne(targetEntity: ExtractedTemplateField::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?ExtractedTemplateField $elnField = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?string $elnJsonldPath = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?string $elnPropertyId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?string $cdeFormFieldId = null;

    #[ORM\Column(type: 'string', enumType: CrosswalkMappingType::class)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private CrosswalkMappingType $mappingType = CrosswalkMappingType::RELATED;

    #[ORM\Column(type: 'string', enumType: MappingStatus::class)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private MappingStatus $mappingStatus = MappingStatus::SUGGESTED;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?float $confidence = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?string $evidence = null;

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private array $datatypeMapping = [];

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private array $unitMapping = [];

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private array $valueMapping = [];

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['field_crosswalk.read', 'field_crosswalk.write', 'template_form_crosswalk.read'])]
    private ?string $curatorNotes = null;

    /**
     * @var Collection<int, ValueCrosswalk>
     */
    #[ORM\OneToMany(
        mappedBy: 'fieldCrosswalk',
        targetEntity: ValueCrosswalk::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Groups(['field_crosswalk.read', 'template_form_crosswalk.read'])]
    private Collection $valueCrosswalks;

    public function __construct()
    {
        $this->valueCrosswalks = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTemplateFormCrosswalk(): TemplateFormCrosswalk
    {
        return $this->templateFormCrosswalk;
    }

    public function setTemplateFormCrosswalk(TemplateFormCrosswalk $templateFormCrosswalk): self
    {
        $this->templateFormCrosswalk = $templateFormCrosswalk;

        return $this;
    }

    public function getCanonicalFormField(): ?CanonicalFormField
    {
        return $this->canonicalFormField;
    }

    public function setCanonicalFormField(?CanonicalFormField $canonicalFormField): self
    {
        $this->canonicalFormField = $canonicalFormField;

        return $this;
    }

    public function getCde(): ?CommonDataElement
    {
        return $this->cde;
    }

    public function setCde(?CommonDataElement $cde): self
    {
        $this->cde = $cde;

        return $this;
    }

    public function getElnField(): ?ExtractedTemplateField
    {
        return $this->elnField;
    }

    public function setElnField(?ExtractedTemplateField $elnField): self
    {
        $this->elnField = $elnField;

        return $this;
    }

    public function getElnJsonldPath(): ?string
    {
        return $this->elnJsonldPath;
    }

    public function setElnJsonldPath(?string $elnJsonldPath): self
    {
        $this->elnJsonldPath = $elnJsonldPath;

        return $this;
    }

    public function getElnPropertyId(): ?string
    {
        return $this->elnPropertyId;
    }

    public function setElnPropertyId(?string $elnPropertyId): self
    {
        $this->elnPropertyId = $elnPropertyId;

        return $this;
    }

    public function getCdeFormFieldId(): ?string
    {
        return $this->cdeFormFieldId;
    }

    public function setCdeFormFieldId(?string $cdeFormFieldId): self
    {
        $this->cdeFormFieldId = $cdeFormFieldId;

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

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getEvidence(): ?string
    {
        return $this->evidence;
    }

    public function setEvidence(?string $evidence): self
    {
        $this->evidence = $evidence;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getDatatypeMapping(): array
    {
        return $this->datatypeMapping;
    }

    /**
     * @param array<int|string, mixed> $datatypeMapping
     */
    public function setDatatypeMapping(array $datatypeMapping): self
    {
        $this->datatypeMapping = $datatypeMapping;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getUnitMapping(): array
    {
        return $this->unitMapping;
    }

    /**
     * @param array<int|string, mixed> $unitMapping
     */
    public function setUnitMapping(array $unitMapping): self
    {
        $this->unitMapping = $unitMapping;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getValueMapping(): array
    {
        return $this->valueMapping;
    }

    /**
     * @param array<int|string, mixed> $valueMapping
     */
    public function setValueMapping(array $valueMapping): self
    {
        $this->valueMapping = $valueMapping;

        return $this;
    }

    public function getCuratorNotes(): ?string
    {
        return $this->curatorNotes;
    }

    public function setCuratorNotes(?string $curatorNotes): self
    {
        $this->curatorNotes = $curatorNotes;

        return $this;
    }

    /**
     * @return Collection<int, ValueCrosswalk>
     */
    public function getValueCrosswalks(): Collection
    {
        return $this->valueCrosswalks;
    }

    public function addValueCrosswalk(ValueCrosswalk $valueCrosswalk): self
    {
        if (!$this->valueCrosswalks->contains($valueCrosswalk)) {
            $this->valueCrosswalks->add($valueCrosswalk);
            $valueCrosswalk->setFieldCrosswalk($this);
        }

        return $this;
    }

    public function removeValueCrosswalk(ValueCrosswalk $valueCrosswalk): self
    {
        $this->valueCrosswalks->removeElement($valueCrosswalk);

        return $this;
    }
}
