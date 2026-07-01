<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

/**
 * Ranks a set of federated search results by how well they match the user's query,
 * using the deterministic embedding + cosine similarity (no AI provider required).
 * Powers the "best match" hint on the Reference Hub search.
 */
final readonly class ReferenceBestMatchService
{
    private const int DEFAULT_LIMIT = 3;

    public function __construct(
        private EmbeddingProviderInterface $embeddings,
    ) {
    }

    /**
     * @param list<mixed> $items federated ReferenceResult payloads (raw decoded JSON)
     *
     * @return list<array{id: string, score: float, reason: string}> ranked best-first
     */
    public function rank(string $query, array $items, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);
        if ('' === $query || [] === $items) {
            return [];
        }

        $queryVector = $this->embeddings->embed($query);

        $scored = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ('' === $id) {
                continue;
            }

            $text = trim((string) ($item['title'] ?? '') . ' ' . (string) ($item['description'] ?? ''));
            $score = VectorMath::cosineSimilarity($queryVector, $this->embeddings->embed($text));
            $scored[] = [
                'id' => $id,
                'score' => round(max(0.0, $score), 4),
                'reason' => $this->reason($score),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return \array_slice($scored, 0, max(1, $limit));
    }

    private function reason(float $score): string
    {
        return match (true) {
            $score >= 0.6 => 'Strong overlap with your query terms.',
            $score >= 0.3 => 'Partial overlap with your query terms.',
            default => 'Weak overlap with your query terms.',
        };
    }
}
