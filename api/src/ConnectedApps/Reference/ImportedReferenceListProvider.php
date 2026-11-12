<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared state provider for both the REST collection (GET /my-references) and the
 * `list_my_references` MCP tool. Returns only the current account's imported
 * references (tenant-scoped via the authenticated user). Reads the `limit`
 * argument from the MCP call (`$context['mcp_data']`) or the REST query string.
 *
 * @implements ProviderInterface<ImportedReferenceResult>
 */
final readonly class ImportedReferenceListProvider implements ProviderInterface
{
    public function __construct(
        private ImportedReferenceRepository $repository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<ImportedReferenceResult>
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

        $limit = (int) ($args['limit'] ?? 50);

        return array_map(
            static fn (ImportedReference $reference): ImportedReferenceResult => new ImportedReferenceResult(
                id: (string) $reference->getId(),
                referenceId: $reference->getReferenceId(),
                source: $reference->getSource(),
                sourceName: $reference->getSourceName(),
                type: $reference->getType(),
                title: $reference->getTitle(),
                description: $reference->getDescription(),
                externalUrl: $reference->getExternalUrl(),
                identifiers: $reference->getIdentifiers() ?? [],
                importedAt: $reference->getImportedAt()->format(\DateTimeInterface::ATOM),
            ),
            $this->repository->findByAccount($user->getAccount(), $limit),
        );
    }
}
