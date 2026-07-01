<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ConnectedApps\Reference\Standardization\ReferenceStandardizationService;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * State provider for the `standardize_references` MCP tool. Resolves the requested
 * federated ids against the current account's imported library, then delegates to
 * {@see ReferenceStandardizationService}. The Compare view uses
 * {@see \App\Controller\Api\ReferenceStandardizeController} instead (it posts the
 * raw results directly), so this path only handles the agent calling over the
 * library it can see.
 *
 * @implements ProviderInterface<StandardizedReference>
 */
final readonly class ReferenceStandardizeProvider implements ProviderInterface
{
    public function __construct(
        private ReferenceStandardizationService $standardizationService,
        private ImportedReferenceRepository $repository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StandardizedReference
    {
        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];
        $referenceIds = $this->referenceIds($args['referenceIds'] ?? '');

        $items = [];
        $user = $this->security->getUser();
        if ($user instanceof User && [] !== $referenceIds) {
            $account = $user->getAccount();
            foreach ($referenceIds as $referenceId) {
                $reference = $this->repository->findByAccountAndReferenceId($account, $referenceId);
                if (null !== $reference) {
                    $items[] = $this->toItem($reference);
                }
            }
        }

        return StandardizedReference::fromResult($this->standardizationService->standardize($items));
    }

    /**
     * @param mixed $raw comma-separated string or list
     *
     * @return list<string>
     */
    private function referenceIds(mixed $raw): array
    {
        $values = \is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $values)));
    }

    /**
     * @return array<string, mixed>
     */
    private function toItem(ImportedReference $reference): array
    {
        return [
            'id' => $reference->getReferenceId(),
            'source' => $reference->getSource(),
            'sourceName' => $reference->getSourceName(),
            'type' => $reference->getType(),
            'title' => $reference->getTitle(),
            'description' => $reference->getDescription(),
            'externalUrl' => $reference->getExternalUrl(),
            'identifiers' => $reference->getIdentifiers() ?? [],
            'raw' => $reference->getRaw() ?? [],
        ];
    }
}
