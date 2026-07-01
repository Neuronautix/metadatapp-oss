<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

/**
 * Pure-PHP vector helpers used by the deterministic (pgvector-free) similarity path.
 *
 * Keeping this here — rather than relying on the database — is what lets the unit
 * tests run without the pgvector extension: {@see ReferenceEmbeddingService} loads
 * candidate rows and ranks them with {@see cosineSimilarity()} in PHP. pgvector is
 * the *scale* path (nearest-neighbour in the DB), never a correctness requirement.
 */
final class VectorMath
{
    /**
     * Cosine similarity of two vectors. Because the embedding providers already
     * return L2-normalized vectors this is effectively a dot product, but we
     * re-normalize defensively so the helper is correct for any input. A zero
     * vector (e.g. token-less text) yields 0.0 rather than a division-by-zero.
     *
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(\count($a), \count($b));
        for ($i = 0; $i < $length; ++$i) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Decode a stored embedding. We persist embeddings as a JSON array of floats in
     * a portable column so the deterministic path needs no pgvector; the optional
     * pgvector column is a parallel, guarded representation (see the migration).
     *
     * @return list<float>|null
     */
    public static function decode(?string $json): ?array
    {
        if (null === $json || '' === $json) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded)) {
            return null;
        }

        $vector = [];
        foreach ($decoded as $value) {
            if (!\is_int($value) && !\is_float($value)) {
                return null;
            }
            $vector[] = (float) $value;
        }

        return $vector;
    }

    /**
     * @param list<float> $vector
     */
    public static function encode(array $vector): string
    {
        // JSON_PRESERVE_ZERO_FRACTION keeps whole-number floats (e.g. 0.0) typed as
        // floats on decode, so the stored vector round-trips identically to a list<float>.
        return json_encode($vector, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
