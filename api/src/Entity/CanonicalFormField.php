<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A source-agnostic field definition inside a CanonicalForm.
 */
#[ORM\Entity]
#[ORM\Table(name: 'crosswalk_canonical_form_field')]
class CanonicalFormField
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['canonical_form_field.read', 'canonical_form.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: CanonicalForm::class, inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CanonicalForm $canonicalForm;

    #[ORM\ManyToOne(targetEntity: CanonicalFormSection::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?CanonicalFormSection $section = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private string $localKey;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?string $datatype = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private bool $required = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?string $unitConstraint = null;

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private array $allowedValues = [];

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private array $ontologyMappings = [];

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?string $jsonldPath = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private ?string $sourceFieldReference = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['canonical_form_field.read', 'canonical_form_field.write', 'canonical_form.read'])]
    private int $orderIndex = 0;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCanonicalForm(): CanonicalForm
    {
        return $this->canonicalForm;
    }

    public function setCanonicalForm(CanonicalForm $canonicalForm): self
    {
        $this->canonicalForm = $canonicalForm;

        return $this;
    }

    public function getSection(): ?CanonicalFormSection
    {
        return $this->section;
    }

    public function setSection(?CanonicalFormSection $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function setLocalKey(string $localKey): self
    {
        $this->localKey = $localKey;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getUnitConstraint(): ?string
    {
        return $this->unitConstraint;
    }

    public function setUnitConstraint(?string $unitConstraint): self
    {
        $this->unitConstraint = $unitConstraint;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getAllowedValues(): array
    {
        return $this->allowedValues;
    }

    /**
     * @param array<int|string, mixed> $allowedValues
     */
    public function setAllowedValues(array $allowedValues): self
    {
        $this->allowedValues = $allowedValues;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOntologyMappings(): array
    {
        return $this->ontologyMappings;
    }

    /**
     * @param array<int|string, mixed> $ontologyMappings
     */
    public function setOntologyMappings(array $ontologyMappings): self
    {
        $this->ontologyMappings = $ontologyMappings;

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

    public function getSourceFieldReference(): ?string
    {
        return $this->sourceFieldReference;
    }

    public function setSourceFieldReference(?string $sourceFieldReference): self
    {
        $this->sourceFieldReference = $sourceFieldReference;

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
}
