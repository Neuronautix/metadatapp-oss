<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

use App\Entity\Account;
use App\Entity\ImportedReference;
use App\Repository\ImportedReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Embeds imported references and finds the nearest library items by cosine
 * similarity — the engine behind "Find similar references".
 *
 * Design: SEMANTIC SIMILARITY HERE IS COMPUTED IN PURE PHP. {@see findSimilar()}
 * loads the account's embedded library rows and ranks them with
 * {@see VectorMath::cosineSimilarity()}. This is what lets the unit tests run with
 * no pgvector extension and keeps the default code path self-contained.
 *
 * pgvector is the *scale* path only: the migration adds a `reference_embedding
 * vector(256)` column + ivfflat index so a large, shared corpus could do
 * nearest-neighbour in the database (`ORDER BY embedding <=> :q`). That path is
 * inert unless the extension + column exist; correctness never depends on it.
 *
 * The embedding provider is {@see EmbeddingProviderInterface}, DI-aliased to the
 * deterministic {@see HashingEmbeddingProvider} by default (so this works with AI
 * off), and swappable for a real model via {@see GatewayEmbeddingProvider}.
 */
final readonly class ReferenceEmbeddingService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddingProvider,
        private ImportedReferenceRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Embed + persist the embedding for a reference (no-op-safe: callers may flush).
     * Returns the computed vector. Idempotent in effect — re-embedding the same text
     * yields the same deterministic vector with the hashing provider.
     *
     * @return list<float>
     */
    public function embedReference(ImportedReference $reference, bool $flush = false): array
    {
        $vector = $this->embeddingProvider->embed($this->referenceText($reference));
        $reference->setEmbedding(VectorMath::encode($vector));

        if ($flush) {
            $this->entityManager->flush();
        }

        return $vector;
    }

    /**
     * Nearest library items to a free-text query, ranked by cosine similarity.
     *
     * @return list<array{reference: ImportedReference, score: float}>
     */
    public function findSimilarToText(Account $account, string $text, int $limit = 5, ?string $excludeReferenceId = null): array
    {
        $text = trim($text);
        if ('' === $text) {
            return [];
        }

        return $this->rank($account, $this->embeddingProvider->embed($text), $limit, $excludeReferenceId);
    }

    /**
     * Nearest library items to an existing reference (excludes the reference itself).
     *
     * @return list<array{reference: ImportedReference, score: float}>
     */
    public function findSimilarToReference(ImportedReference $reference, int $limit = 5): array
    {
        $stored = VectorMath::decode($reference->getEmbedding());
        $query = $stored ?? $this->embeddingProvider->embed($this->referenceText($reference));

        return $this->rank($reference->getAccount(), $query, $limit, $reference->getReferenceId());
    }

    /**
     * @param list<float> $query
     *
     * @return list<array{reference: ImportedReference, score: float}>
     */
    private function rank(Account $account, array $query, int $limit, ?string $excludeReferenceId): array
    {
        $limit = max(1, min(50, $limit));

        $scored = [];
        foreach ($this->repository->findAllForAccount($account) as $candidate) {
            if (null !== $excludeReferenceId && $candidate->getReferenceId() === $excludeReferenceId) {
                continue;
            }

            // Embed candidates lazily so similarity works even for references stored
            // before embeddings existed (deterministic provider → no network cost).
            $vector = VectorMath::decode($candidate->getEmbedding())
                ?? $this->embeddingProvider->embed($this->referenceText($candidate));

            $scored[] = [
                'reference' => $candidate,
                'score' => VectorMath::cosineSimilarity($query, $vector),
            ];
        }

        // Stable, deterministic ordering: score desc, then reference id for ties.
        usort($scored, static function (array $a, array $b): int {
            $byScore = $b['score'] <=> $a['score'];

            return 0 !== $byScore ? $byScore : strcmp($a['reference']->getReferenceId(), $b['reference']->getReferenceId());
        });

        return \array_slice($scored, 0, $limit);
    }

    /**
     * The text we embed for a reference: title + description + canonical/cluster
     * enrichment when present, so semantically related items (synonyms, the same
     * canonical term) land near each other.
     */
    private function referenceText(ImportedReference $reference): string
    {
        $parts = [
            $reference->getTitle(),
            $reference->getDescription() ?? '',
            $reference->getCanonicalTermLabel() ?? '',
            $reference->getType(),
        ];

        return trim(implode(' ', array_filter($parts, static fn (string $p): bool => '' !== trim($p))));
    }
}
