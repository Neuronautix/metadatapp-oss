<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\ImportSessionStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            uriTemplate: '/curation/sessions',
            processor: \App\State\Processor\SessionUploadProcessor::class,
            deserialize: false,
        ),
        new Get(
            uriTemplate: '/curation/sessions/{id}/summary',
            provider: \App\State\Provider\SessionSummaryProvider::class,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/curation/sessions/{id}/review-summary',
            provider: \App\State\Provider\SessionSummaryProvider::class,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/curation/sessions/{id}/resolution-summary',
            provider: \App\State\Provider\SessionSummaryProvider::class,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/curation/sessions/{id}/patch-summary',
            provider: \App\State\Provider\SessionSummaryProvider::class,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/curation/sessions/{id}/profile',
            processor: \App\State\Processor\SessionWorkflowProcessor::class,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/curation/sessions/{id}/generate',
            processor: \App\State\Processor\SessionWorkflowProcessor::class,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/curation/sessions/{id}/patch-build',
            processor: \App\State\Processor\SessionWorkflowProcessor::class,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/curation/sessions/{id}/apply',
            processor: \App\State\Processor\SessionWorkflowProcessor::class,
            deserialize: false,
        ),
    ],
    normalizationContext: [
        AbstractNormalizer::GROUPS => ['import_session.read', 'enum:read'],
    ]
)]
class ImportSession implements AccountAwareInterface, UserAwareInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[Groups(['import_session.read'])]
    /** @phpstan-ignore property.unusedType */
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', enumType: ImportSessionStatus::class)]
    #[Groups(['import_session.read'])]
    private ImportSessionStatus $status = ImportSessionStatus::Uploaded;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['import_session.read'])]
    private string $sourceFileFingerprint;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['import_session.read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $profiledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $schemaMappingsGeneratedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $normalizationsGeneratedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $vocabularyGeneratedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $patchesBuiltAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['import_session.read'])]
    private ?\DateTimeImmutable $appliedAt = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStatus(): ImportSessionStatus
    {
        return $this->status;
    }

    public function setStatus(ImportSessionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSourceFileFingerprint(): string
    {
        return $this->sourceFileFingerprint;
    }

    public function setSourceFileFingerprint(string $sourceFileFingerprint): self
    {
        $this->sourceFileFingerprint = $sourceFileFingerprint;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProfiledAt(): ?\DateTimeImmutable
    {
        return $this->profiledAt;
    }

    public function setProfiledAt(?\DateTimeImmutable $profiledAt): self
    {
        $this->profiledAt = $profiledAt;

        return $this;
    }

    public function getSchemaMappingsGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->schemaMappingsGeneratedAt;
    }

    public function setSchemaMappingsGeneratedAt(?\DateTimeImmutable $schemaMappingsGeneratedAt): self
    {
        $this->schemaMappingsGeneratedAt = $schemaMappingsGeneratedAt;

        return $this;
    }

    public function getNormalizationsGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->normalizationsGeneratedAt;
    }

    public function setNormalizationsGeneratedAt(?\DateTimeImmutable $normalizationsGeneratedAt): self
    {
        $this->normalizationsGeneratedAt = $normalizationsGeneratedAt;

        return $this;
    }

    public function getVocabularyGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->vocabularyGeneratedAt;
    }

    public function setVocabularyGeneratedAt(?\DateTimeImmutable $vocabularyGeneratedAt): self
    {
        $this->vocabularyGeneratedAt = $vocabularyGeneratedAt;

        return $this;
    }

    public function getPatchesBuiltAt(): ?\DateTimeImmutable
    {
        return $this->patchesBuiltAt;
    }

    public function setPatchesBuiltAt(?\DateTimeImmutable $patchesBuiltAt): self
    {
        $this->patchesBuiltAt = $patchesBuiltAt;

        return $this;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): self
    {
        $this->appliedAt = $appliedAt;

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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
