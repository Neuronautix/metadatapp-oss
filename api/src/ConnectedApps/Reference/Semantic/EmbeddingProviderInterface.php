<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

/**
 * Turns free text into a dense numeric vector for semantic similarity.
 *
 * Two implementations ship:
 *   - {@see HashingEmbeddingProvider}  — pure, deterministic, no network. The
 *     default (DI-aliased in services.yaml) and the only impl exercised by tests.
 *   - {@see GatewayEmbeddingProvider}  — guarded hook for a real embeddings model,
 *     selected only when an AI provider is configured + the semantic flag is on.
 *
 * Every implementation MUST return an L2-normalized vector of {@see DIMENSIONS}
 * length so cosine similarity reduces to a plain dot product, and so vectors from
 * different providers are never mixed (the dimension is fixed and shared).
 */
interface EmbeddingProviderInterface
{
    /**
     * Fixed embedding width. Matches the pgvector column (`vector(256)`) declared
     * in the migration. Changing this requires a new migration + a re-embed.
     */
    public const int DIMENSIONS = 256;

    /**
     * @return list<float> an L2-normalized vector of exactly self::DIMENSIONS floats
     */
    public function embed(string $text): array;
}
