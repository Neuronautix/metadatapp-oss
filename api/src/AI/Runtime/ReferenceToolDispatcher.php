<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Mcp\McpToolCallResult;
use App\ConnectedApps\Reference\ReferenceImportService;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchService;
use App\ConnectedApps\Reference\Standardization\ReferenceStandardizationService;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Production {@see ToolDispatcherInterface} for the Reference Hub tools used by the
 * agentic loop: the read tools `search_reference_resources` and
 * `list_my_references`, and the write tool `import_reference`. Each call is timed
 * and wrapped as a {@see McpToolCallResult} (mirroring the MCP bridge), with errors
 * sanitized so upstream details never leak to the client.
 *
 * Authorization (read/write + human-approval gating) is the orchestrator's job;
 * this dispatcher only executes calls it has been handed.
 */
final readonly class ReferenceToolDispatcher implements ToolDispatcherInterface
{
    private const TOOL_CALL_FAILED_MESSAGE = 'Tool call failed.';

    public function __construct(
        private ReferenceSearchService $searchService,
        private ReferenceImportService $importService,
        private ImportedReferenceRepository $importedReferenceRepository,
        private ReferenceStandardizationService $standardizationService,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(string $toolName, array $arguments): McpToolCallResult
    {
        $start = microtime(true);

        try {
            $data = match ($toolName) {
                'search_reference_resources' => $this->search($arguments),
                'list_my_references' => $this->listMyReferences($arguments),
                'standardize_references' => $this->standardize($arguments),
                'import_reference' => $this->import($arguments),
                default => throw new \InvalidArgumentException(\sprintf('No dispatcher handler for tool "%s".', $toolName)),
            };

            return new McpToolCallResult(
                toolName: $toolName,
                success: true,
                data: $data,
                callDurationMs: $this->elapsedMs($start),
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Reference tool dispatch failed.', [
                'tool' => $toolName,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return new McpToolCallResult(
                toolName: $toolName,
                success: false,
                error: self::TOOL_CALL_FAILED_MESSAGE,
                callDurationMs: $this->elapsedMs($start),
            );
        }
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function search(array $arguments): array
    {
        $query = \is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $limit = (int) ($arguments['limit'] ?? 20);
        $appsRaw = $arguments['apps'] ?? '';
        $appCodes = \is_array($appsRaw)
            ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $appsRaw)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $appsRaw))));

        $results = $this->searchService->search($query, $appCodes, $limit);

        return [
            'count' => \count($results),
            'results' => array_map(
                static fn (ReferenceResult $r): array => [
                    'id' => $r->id,
                    'source' => $r->source,
                    'sourceName' => $r->sourceName,
                    'type' => $r->type,
                    'title' => $r->title,
                    'externalUrl' => $r->externalUrl,
                ],
                $results,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function listMyReferences(array $arguments): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['count' => 0, 'references' => []];
        }

        $limit = (int) ($arguments['limit'] ?? 50);
        $references = $this->importedReferenceRepository->findByAccount($user->getAccount(), $limit);

        return [
            'count' => \count($references),
            'references' => array_map(
                static fn (ImportedReference $r): array => [
                    'id' => (string) $r->getId(),
                    'referenceId' => $r->getReferenceId(),
                    'source' => $r->getSource(),
                    'sourceName' => $r->getSourceName(),
                    'type' => $r->getType(),
                    'title' => $r->getTitle(),
                    'externalUrl' => $r->getExternalUrl(),
                ],
                $references,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function standardize(array $arguments): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['aiAvailable' => false, 'clusters' => []];
        }

        $referenceIds = \is_array($arguments['referenceIds'] ?? null)
            ? $arguments['referenceIds']
            : explode(',', (string) ($arguments['referenceIds'] ?? ''));
        $referenceIds = array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $referenceIds)));

        $account = $user->getAccount();
        $items = [];
        foreach ($referenceIds as $referenceId) {
            $reference = $this->importedReferenceRepository->findByAccountAndReferenceId($account, $referenceId);
            if (null !== $reference) {
                $items[] = $this->toItem($reference);
            }
        }

        return $this->standardizationService->standardize($items)->toArray();
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

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function import(array $arguments): array
    {
        $items = \is_array($arguments['items'] ?? null) ? array_values($arguments['items']) : [];
        $saved = $this->importService->import($items);

        return [
            'count' => \count($saved),
            'imported' => array_map(
                static fn (ImportedReference $r): array => [
                    'id' => (string) $r->getId(),
                    'referenceId' => $r->getReferenceId(),
                    'title' => $r->getTitle(),
                ],
                $saved,
            ),
        ];
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
