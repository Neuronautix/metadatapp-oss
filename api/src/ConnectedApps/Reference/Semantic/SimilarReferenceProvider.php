<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Semantic;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State provider for both the REST collection (GET /similar-references) and the
 * `find_similar_references` MCP tool. Reads the query/referenceId from the MCP call
 * (`$context['mcp_data']`) or the REST query string, scopes strictly to the current
 * account's library, and delegates ranking to {@see ReferenceEmbeddingService}
 * (pure-PHP cosine — no pgvector, works with AI off).
 *
 * @implements ProviderInterface<SimilarReference>
 */
final readonly class SimilarReferenceProvider implements ProviderInterface
{
    public function __construct(
        private ReferenceEmbeddingService $embeddingService,
        private ImportedReferenceRepository $repository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<SimilarReference>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];
        if ([] === $args) {
            $args = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
        }

        $query = $this->stringArg($args, ['query', 'q']);
        $referenceId = $this->stringArg($args, ['referenceId']);
        $limit = (int) ($args['limit'] ?? 5);

        $account = $user->getAccount();

        $matches = [];
        if ('' !== $referenceId) {
            $reference = $this->repository->findByAccountAndReferenceId($account, $referenceId);
            if (null !== $reference) {
                $matches = $this->embeddingService->findSimilarToReference($reference, $limit);
            }
        } elseif ('' !== $query) {
            $matches = $this->embeddingService->findSimilarToText($account, $query, $limit);
        }

        return array_map(
            static fn (array $match): SimilarReference => self::toResource($match['reference'], $match['score']),
            $matches,
        );
    }

    private static function toResource(ImportedReference $reference, float $score): SimilarReference
    {
        return new SimilarReference(
            id: $reference->getReferenceId(),
            source: $reference->getSource(),
            sourceName: $reference->getSourceName(),
            type: $reference->getType(),
            title: $reference->getTitle(),
            description: $reference->getDescription(),
            externalUrl: $reference->getExternalUrl(),
            score: round($score, 4),
            identifiers: $reference->getIdentifiers() ?? [],
        );
    }

    /**
     * @param array<string, mixed> $args
     * @param list<string>         $keys
     */
    private function stringArg(array $args, array $keys): string
    {
        foreach ($keys as $key) {
            if (\is_string($args[$key] ?? null) && '' !== trim($args[$key])) {
                return trim($args[$key]);
            }
        }

        return '';
    }
}
