<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A logical grouping of fields inside a CanonicalForm.
 */
#[ORM\Entity]
#[ORM\Table(name: 'crosswalk_canonical_form_section')]
class CanonicalFormSection
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['canonical_form_section.read', 'canonical_form.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: CanonicalForm::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CanonicalForm $canonicalForm;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['canonical_form_section.read', 'canonical_form_section.write', 'canonical_form.read'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['canonical_form_section.read', 'canonical_form_section.write', 'canonical_form.read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['canonical_form_section.read', 'canonical_form_section.write', 'canonical_form.read'])]
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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
