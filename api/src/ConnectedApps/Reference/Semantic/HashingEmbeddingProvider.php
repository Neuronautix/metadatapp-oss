<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

/**
 * Default, pure, deterministic embedding: a token-hashed bag-of-words vector,
 * L2-normalized. No network, no model, no state — the same text always maps to the
 * same vector, which makes it the safe default when AI is off and the only provider
 * the unit tests need.
 *
 * It is NOT a semantic model: similarity here reflects lexical (token) overlap, not
 * meaning. That is intentional — it gives the "find similar references" feature a
 * working, free, offline baseline (a sparse hashing vectorizer, like the "hashing
 * trick" used by scikit-learn's HashingVectorizer) and degrades gracefully. When a
 * real embeddings provider is configured, {@see GatewayEmbeddingProvider} replaces
 * this for true semantic similarity, while the storage + cosine math stay identical.
 *
 * The vector is built by:
 *   1. lower-casing + splitting the text into alphanumeric word tokens,
 *   2. hashing each token into one of DIMENSIONS buckets (with a sign hash so
 *      collisions can cancel rather than always reinforce — standard signed
 *      hashing-trick practice),
 *   3. accumulating term frequencies, then L2-normalizing.
 */
final class HashingEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        $dimensions = self::DIMENSIONS;
        $vector = array_fill(0, $dimensions, 0.0);

        foreach ($this->tokenize($text) as $token) {
            // Two independent hashes: one for the bucket, one for the sign. crc32 is
            // deterministic across processes/platforms (unlike spl_object or random
            // seeds), which is exactly what we need for stable, reproducible vectors.
            // abs() guards against a negative modulo on 32-bit platforms (crc32 can
            // exceed PHP_INT_MAX there), keeping the bucket within [0, dimensions).
            $bucket = abs(crc32($token)) % $dimensions;
            $sign = (1 === (crc32('sign:' . $token) & 1)) ? 1.0 : -1.0;
            $vector[$bucket] += $sign;
        }

        return $this->l2Normalize(array_values($vector));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $lowered = mb_strtolower(trim($text));
        if ('' === $lowered) {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $lowered, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $parts) {
            return [];
        }

        return array_values(array_filter($parts, static fn (string $token): bool => '' !== $token));
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    private function l2Normalize(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        $norm = sqrt($norm);

        if ($norm <= 0.0) {
            // Empty / token-less text → zero vector. Cosine similarity with it is
            // defined as 0 by the similarity helper, so this is safe.
            return $vector;
        }

        return array_map(static fn (float $value): float => $value / $norm, $vector);
    }
}
