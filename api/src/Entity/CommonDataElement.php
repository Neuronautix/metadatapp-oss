<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\CdeSource;
use App\Enum\CdeStatus;
use App\Repository\CommonDataElementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A Common Data Element: a versioned, source-aware, semantic field definition
 * (NIH CDE, Metadatapp local, HCMO, MBO, custom). Mappings target CDEs, not
 * labels. Raw payload and provenance are preserved verbatim.
 */
#[ORM\Entity(repositoryClass: CommonDataElementRepository::class)]
#[ORM\Table(name: 'crosswalk_common_data_element')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/cdes',
        ),
        new Post(
            uriTemplate: '/cdes',
        ),
        new Get(
            uriTemplate: '/cdes/{id}',
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['common_data_element.read'],
    ],
    denormalizationContext: [
        AbstractNormalizer::GROUPS => ['common_data_element.write'],
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['label' => 'partial', 'source' => 'exact'])]
class CommonDataElement implements AccountAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['common_data_element.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', enumType: CdeSource::class)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private CdeSource $source = CdeSource::METADATAPP;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    #[Assert\Length(max: 1024)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $version = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $definition = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $questionText = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $datatype = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $unitConstraint = null;

    /**
     * Item shape: {value: string, code?: string, ontologyTerm?: string}.
     *
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private array $permissibleValues = [];

    /**
     * Item shape: {iri: string, label?: string, source?: string}.
     *
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private array $ontologyMappings = [];

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private array $provenance = [];

    /**
     * @var array<int|string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['common_data_element.read'])]
    private ?array $rawPayload = null;

    #[ORM\Column(type: 'string', enumType: CdeStatus::class)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private CdeStatus $status = CdeStatus::DRAFT;

    /**
     * Stable slug used in JSON-LD @id, e.g. molecular_weight.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['common_data_element.read', 'common_data_element.write'])]
    private ?string $localKey = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSource(): CdeSource
    {
        return $this->source;
    }

    public function setSource(CdeSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

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

    public function getQuestionText(): ?string
    {
        return $this->questionText;
    }

    public function setQuestionText(?string $questionText): self
    {
        $this->questionText = $questionText;

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
    public function getPermissibleValues(): array
    {
        return $this->permissibleValues;
    }

    /**
     * @param array<int|string, mixed> $permissibleValues
     */
    public function setPermissibleValues(array $permissibleValues): self
    {
        $this->permissibleValues = $permissibleValues;

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

    /**
     * @return array<int|string, mixed>
     */
    public function getProvenance(): array
    {
        return $this->provenance;
    }

    /**
     * @param array<int|string, mixed> $provenance
     */
    public function setProvenance(array $provenance): self
    {
        $this->provenance = $provenance;

        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    /**
     * @param array<int|string, mixed>|null $rawPayload
     */
    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    public function getStatus(): CdeStatus
    {
        return $this->status;
    }

    public function setStatus(CdeStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLocalKey(): ?string
    {
        return $this->localKey;
    }

    public function setLocalKey(?string $localKey): self
    {
        $this->localKey = $localKey;

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
