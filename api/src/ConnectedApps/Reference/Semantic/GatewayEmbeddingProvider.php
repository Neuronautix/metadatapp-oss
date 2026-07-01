<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

use App\AI\Governance\AiFeatureFlags;
use Psr\Log\LoggerInterface;

/**
 * Guarded hook for a real embeddings model (e.g. OpenAI text-embedding-3,
 * Anthropic/Voyage, a local sentence-transformer behind an HTTP gateway).
 *
 * IMPORTANT: this is intentionally a STUB. It is NOT wired into the DI container by
 * default — {@see EmbeddingProviderInterface} is aliased to
 * {@see HashingEmbeddingProvider} in services.yaml. This class exists so a real
 * provider can be dropped in later without touching call-sites: it owns the
 * governance gate (semantic flag + a configured AI provider) and the fall-through
 * to the deterministic baseline.
 *
 * To activate a real provider you would:
 *   1. inject an embeddings client and call it inside {@see callProvider()},
 *   2. re-alias EmbeddingProviderInterface to this class (guarded by the flag),
 *   3. ensure the returned vector is L2-normalized to DIMENSIONS length.
 *
 * Until then it degrades to the hashing baseline so behaviour never breaks and the
 * default (AI-off) path is unchanged.
 */
final readonly class GatewayEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private AiFeatureFlags $featureFlags,
        private HashingEmbeddingProvider $fallback,
        private LoggerInterface $logger,
        /** Master switch for real embeddings; false keeps this inert. */
        private bool $realEmbeddingsEnabled = false,
    ) {
    }

    public function embed(string $text): array
    {
        if (!$this->canUseRealEmbeddings()) {
            return $this->fallback->embed($text);
        }

        try {
            $vector = $this->callProvider($text);
            if (null !== $vector) {
                return $vector;
            }
        } catch (\Throwable $e) {
            // Network/provider errors must never break similarity — degrade.
            $this->logger->warning('Embedding provider failed; using deterministic fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->fallback->embed($text);
    }

    private function canUseRealEmbeddings(): bool
    {
        // Real embeddings require BOTH the explicit switch and an enabled AI
        // assistant (a configured provider). Off by default on every axis.
        return $this->realEmbeddingsEnabled && $this->featureFlags->isAssistantEnabled();
    }

    /**
     * Stubbed call to a real embeddings provider. Returns null today so we always
     * fall through to the deterministic baseline. Implement when a provider is
     * available; the result MUST be an L2-normalized vector of DIMENSIONS length.
     *
     * @return list<float>|null
     */
    private function callProvider(string $text): ?array // @phpstan-ignore return.unusedType (forward-looking type; the stub only returns null today)
    {
        // Deliberately unimplemented — see the class docblock.
        unset($text);

        return null;
    }
}
