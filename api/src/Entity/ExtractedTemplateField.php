<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A single field extracted from an imported ELN template. This is the
 * "do not discard unmapped fields" store: every node walked during import
 * becomes one ExtractedTemplateField, mapped or not.
 */
#[ORM\Entity]
#[ORM\Table(name: 'crosswalk_extracted_template_field')]
class ExtractedTemplateField
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['extracted_template_field.read', 'external_template.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ExternalTemplate::class, inversedBy: 'extractedFields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExternalTemplate $externalTemplate;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $jsonldId = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $jsonldPath = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private string $label;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $propertyId = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $datatype = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $exampleValue = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private ?string $unit = null;

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private array $ontologyTerms = [];

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['extracted_template_field.read', 'external_template.read'])]
    private array $rawNode = [];

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private int $orderIndex = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['extracted_template_field.read', 'extracted_template_field.write', 'external_template.read'])]
    private bool $mapped = false;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getExternalTemplate(): ExternalTemplate
    {
        return $this->externalTemplate;
    }

    public function setExternalTemplate(ExternalTemplate $externalTemplate): self
    {
        $this->externalTemplate = $externalTemplate;

        return $this;
    }

    public function getJsonldId(): ?string
    {
        return $this->jsonldId;
    }

    public function setJsonldId(?string $jsonldId): self
    {
        $this->jsonldId = $jsonldId;

        return $this;
    }

    public function getJsonldPath(): ?string
    {
        return $this->jsonldPath;
    }

    public function setJsonldPath(?string $jsonldPath): self
    {
        $this->jsonldPath = $jsonldPath;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPropertyId(): ?string
    {
        return $this->propertyId;
    }

    public function setPropertyId(?string $propertyId): self
    {
        $this->propertyId = $propertyId;

        return $this;
    }

    public function getDatatype(): ?string
    {
        return $this->datatype;
    }

    public function setDatatype(?string $datatype): self
    {
        $this->datatype = $datatype;

        return $this;
    }

    public function getExampleValue(): ?string
    {
        return $this->exampleValue;
    }

    public function setExampleValue(?string $exampleValue): self
    {
        $this->exampleValue = $exampleValue;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOntologyTerms(): array
    {
        return $this->ontologyTerms;
    }

    /**
     * @param array<int|string, mixed> $ontologyTerms
     */
    public function setOntologyTerms(array $ontologyTerms): self
    {
        $this->ontologyTerms = $ontologyTerms;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getRawNode(): array
    {
        return $this->rawNode;
    }

    /**
     * @param array<int|string, mixed> $rawNode
     */
    public function setRawNode(array $rawNode): self
    {
        $this->rawNode = $rawNode;

        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function isMapped(): bool
    {
        return $this->mapped;
    }

    public function setMapped(bool $mapped): self
    {
        $this->mapped = $mapped;

        return $this;
    }
}
