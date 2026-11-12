<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use App\ConnectedApps\Reference\Semantic\ReferenceEmbeddingService;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Persists federated Reference Hub results into the current account's library,
 * upserting by (account, referenceId) so re-importing the same resource updates
 * rather than duplicates. Provenance (source, externalUrl, identifiers, raw) is
 * preserved so the saved record keeps its exact link to the source of truth.
 */
final readonly class ReferenceImportService
{
    public function __construct(
        private ImportedReferenceRepository $repository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ReferenceEmbeddingService $embeddingService,
    ) {
    }

    /**
     * @param list<mixed> $items federated ReferenceResult payloads (raw decoded JSON)
     *
     * @return list<ImportedReference>
     */
    public function import(array $items): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }
        $account = $user->getAccount();

        $saved = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $referenceId = trim((string) ($item['id'] ?? ''));
            if ('' === $referenceId) {
                continue;
            }

            $reference = $this->repository->findByAccountAndReferenceId($account, $referenceId)
                ?? (new ImportedReference())->setAccount($account)->setReferenceId($referenceId);

            $reference
                ->setSource($this->clip((string) ($item['source'] ?? 'unknown'), 64))
                ->setSourceName($this->clip((string) ($item['sourceName'] ?? ($item['source'] ?? 'Unknown')), 255))
                ->setType($this->clip((string) ($item['type'] ?? 'unknown'), 64))
                ->setTitle($this->clip((string) ($item['title'] ?? $referenceId), 512))
                ->setDescription($this->stringOrNull($item['description'] ?? null))
                ->setExternalUrl($this->stringOrNull($item['externalUrl'] ?? null, 2048))
                ->setIdentifiers(\is_array($item['identifiers'] ?? null) ? $item['identifiers'] : null)
                ->setRaw(\is_array($item['raw'] ?? null) ? $item['raw'] : null)
                ->setImportedAt(new \DateTimeImmutable())
            ;

            $this->applyStandardization($reference, $item);

            // Populate the semantic embedding eagerly. The default provider is the
            // pure, deterministic HashingEmbeddingProvider (no network), so this is
            // safe even with the semantic flag off — the stored vector is simply
            // unused until "Find similar" / find_similar_references is invoked.
            $this->embeddingService->embedReference($reference);

            $this->entityManager->persist($reference);
            $saved[] = $reference;
        }

        $this->entityManager->flush();

        return $saved;
    }

    /**
     * Persist AI standardization metadata when the imported item carries it (the
     * "Import standardized" path on the Compare view). Plain imports leave these
     * fields untouched.
     *
     * @param array<string, mixed> $item
     */
    private function applyStandardization(ImportedReference $reference, array $item): void
    {
        $canonicalTerm = \is_array($item['canonicalTerm'] ?? null) ? $item['canonicalTerm'] : null;
        $clusterId = $this->stringOrNull($item['clusterId'] ?? null, 64);
        if (null === $canonicalTerm && null === $clusterId) {
            return;
        }

        if (null !== $canonicalTerm) {
            $reference
                ->setCanonicalTermIri($this->stringOrNull($canonicalTerm['iri'] ?? null, 1024))
                ->setCanonicalTermLabel($this->stringOrNull($canonicalTerm['label'] ?? null, 512))
                ->setCanonicalTermOntology($this->stringOrNull($canonicalTerm['ontology'] ?? null, 64))
            ;
            if (isset($canonicalTerm['confidence']) && is_numeric($canonicalTerm['confidence'])) {
                $reference->setStandardizationConfidence((float) $canonicalTerm['confidence']);
            }
        }

        $reference
            ->setClusterId($clusterId)
            ->setStandardizationEvidence($this->stringListOrNull($item['standardizationEvidence'] ?? null))
            ->setStandardizedAt(new \DateTimeImmutable())
        ;
    }

    /**
     * @return list<string>|null
     */
    private function stringListOrNull(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $list = array_values(array_filter(
            array_map(static fn (mixed $entry): string => \is_scalar($entry) ? trim((string) $entry) : '', $value),
            static fn (string $entry): bool => '' !== $entry,
        ));

        return [] === $list ? null : $list;
    }

    private function clip(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }

    private function stringOrNull(mixed $value, ?int $max = null): ?string
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return null === $max ? $value : mb_substr($value, 0, $max);
    }
}
