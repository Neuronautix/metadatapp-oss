<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\PromoteReferenceToCdeController;
use App\Controller\Api\ReferenceImportController;
use App\Repository\ImportedReferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A reference resource imported from the federated Reference Hub into the account's
 * library. Stores the normalized result plus full provenance (source app, external
 * URL, identifiers, raw record) so it can be reused while keeping the exact link to
 * the source of truth. Idempotent per (account, referenceId).
 */
#[ORM\Entity(repositoryClass: ImportedReferenceRepository::class)]
#[ORM\Table(name: 'imported_reference')]
#[ORM\UniqueConstraint(name: 'uniq_imported_reference_account_ref', columns: ['account_id', 'reference_id'])]
#[ApiResource(
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => ['imported_reference.read']],
    operations: [
        new GetCollection(),
        new Get(),
        new Delete(),
        new Post(
            uriTemplate: '/imported_references/import',
            name: 'imported_reference_import',
            controller: ReferenceImportController::class,
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/imported_references/{id}/promote-to-cde',
            name: 'imported_reference_promote_to_cde',
            controller: PromoteReferenceToCdeController::class,
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
    ],
)]
class ImportedReference implements AccountAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['imported_reference.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private Account $account;

    /** Stable federated id, e.g. "jax_phenome:measure:43003". */
    #[ORM\Column(name: 'reference_id', type: 'string', length: 255)]
    #[Groups(['imported_reference.read'])]
    private string $referenceId;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['imported_reference.read'])]
    private string $source;

    #[ORM\Column(name: 'source_name', type: 'string', length: 255)]
    #[Groups(['imported_reference.read'])]
    private string $sourceName;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['imported_reference.read'])]
    private string $type;

    #[ORM\Column(type: 'string', length: 512)]
    #[Groups(['imported_reference.read'])]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $description = null;

    #[ORM\Column(name: 'external_url', type: 'string', length: 2048, nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $externalUrl = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?array $identifiers = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?array $raw = null;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    #[Groups(['imported_reference.read'])]
    private \DateTimeImmutable $importedAt;

    /** Canonical ontology term IRI chosen by AI standardization, e.g. a VT/MP/MA/EFO PURL. */
    #[ORM\Column(name: 'canonical_term_iri', type: 'string', length: 1024, nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $canonicalTermIri = null;

    #[ORM\Column(name: 'canonical_term_label', type: 'string', length: 512, nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $canonicalTermLabel = null;

    /** Ontology short name of the canonical term, e.g. "vt", "mp", "efo". */
    #[ORM\Column(name: 'canonical_term_ontology', type: 'string', length: 64, nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $canonicalTermOntology = null;

    #[ORM\Column(name: 'standardization_confidence', type: 'float', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?float $standardizationConfidence = null;

    /** Cluster id this reference was grouped into during standardization. */
    #[ORM\Column(name: 'cluster_id', type: 'string', length: 64, nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?string $clusterId = null;

    /** @var list<string>|null Human-readable evidence for the canonical mapping. */
    #[ORM\Column(name: 'standardization_evidence', type: 'json', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?array $standardizationEvidence = null;

    #[ORM\Column(name: 'standardized_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['imported_reference.read'])]
    private ?\DateTimeImmutable $standardizedAt = null;

    /**
     * Semantic-discovery embedding, stored as a portable JSON array of floats so the
     * default similarity path needs no pgvector (the optional `reference_embedding`
     * pgvector column added by the migration is a parallel, guarded scale path).
     * Not serialized to the API. Nullable: present only once embedded.
     */
    #[ORM\Column(name: 'embedding', type: 'text', nullable: true)]
    private ?string $embedding = null;

    public function __construct()
    {
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getReferenceId(): string
    {
        return $this->referenceId;
    }

    public function setReferenceId(string $referenceId): self
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function setSourceName(string $sourceName): self
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

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

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getIdentifiers(): ?array
    {
        return $this->identifiers;
    }

    /**
     * @param array<string, mixed>|null $identifiers
     */
    public function setIdentifiers(?array $identifiers): self
    {
        $this->identifiers = $identifiers;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRaw(): ?array
    {
        return $this->raw;
    }

    /**
     * @param array<string, mixed>|null $raw
     */
    public function setRaw(?array $raw): self
    {
        $this->raw = $raw;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(\DateTimeImmutable $importedAt): self
    {
        $this->importedAt = $importedAt;

        return $this;
    }

    public function getCanonicalTermIri(): ?string
    {
        return $this->canonicalTermIri;
    }

    public function setCanonicalTermIri(?string $canonicalTermIri): self
    {
        $this->canonicalTermIri = $canonicalTermIri;

        return $this;
    }

    public function getCanonicalTermLabel(): ?string
    {
        return $this->canonicalTermLabel;
    }

    public function setCanonicalTermLabel(?string $canonicalTermLabel): self
    {
        $this->canonicalTermLabel = $canonicalTermLabel;

        return $this;
    }

    public function getCanonicalTermOntology(): ?string
    {
        return $this->canonicalTermOntology;
    }

    public function setCanonicalTermOntology(?string $canonicalTermOntology): self
    {
        $this->canonicalTermOntology = $canonicalTermOntology;

        return $this;
    }

    public function getStandardizationConfidence(): ?float
    {
        return $this->standardizationConfidence;
    }

    public function setStandardizationConfidence(?float $standardizationConfidence): self
    {
        $this->standardizationConfidence = $standardizationConfidence;

        return $this;
    }

    public function getClusterId(): ?string
    {
        return $this->clusterId;
    }

    public function setClusterId(?string $clusterId): self
    {
        $this->clusterId = $clusterId;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getStandardizationEvidence(): ?array
    {
        return $this->standardizationEvidence;
    }

    /**
     * @param list<string>|null $standardizationEvidence
     */
    public function setStandardizationEvidence(?array $standardizationEvidence): self
    {
        $this->standardizationEvidence = $standardizationEvidence;

        return $this;
    }

    public function getStandardizedAt(): ?\DateTimeImmutable
    {
        return $this->standardizedAt;
    }

    public function setStandardizedAt(?\DateTimeImmutable $standardizedAt): self
    {
        $this->standardizedAt = $standardizedAt;

        return $this;
    }

    /** Raw stored embedding (JSON array of floats), or null if not yet embedded. */
    public function getEmbedding(): ?string
    {
        return $this->embedding;
    }

    public function setEmbedding(?string $embedding): self
    {
        $this->embedding = $embedding;

        return $this;
    }
}
