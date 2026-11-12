<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Impc\Client\ImpcClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

/**
 * Maps IMPC/IMPReSS standardized phenotyping procedures (keyword search over the
 * IMPC Solr core) into reference results.
 */
final readonly class ImpcReferenceAdapter implements ReferenceSearchAdapterInterface
{
    private const string PUBLIC_SITE = 'https://www.mousephenotype.org';

    public function __construct(private ImpcClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::Impc === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $results = [];
        $procedures = $this->client->searchProcedures($connectedApp, $query, $limit, 0);

        foreach ($procedures['procedures'] as $procedure) {
            $stableId = $procedure['procedureId'] ?? $procedure['stableKey'] ?? null;
            $procId = $procedure['procID'] ?? null;
            if (null === $stableId) {
                continue;
            }
            $results[] = new ReferenceResult(
                id: 'impc:procedure:' . $stableId,
                source: AppCode::Impc->value,
                sourceName: $connectedApp->getName(),
                type: 'procedure',
                title: \is_string($procedure['name'] ?? null) ? $procedure['name'] : (string) $stableId,
                subtitle: \is_string($procedure['pipelineName'] ?? null) ? $procedure['pipelineName'] : null,
                description: \is_string($procedure['description'] ?? null) ? $procedure['description'] : null,
                externalUrl: null !== $procId
                    ? self::PUBLIC_SITE . '/impress/protocol/' . rawurlencode((string) $procId)
                    : self::PUBLIC_SITE . '/impress/procedures/' . rawurlencode((string) $stableId),
                identifiers: array_filter([
                    'procedureKey' => $procedure['procedureKey'] ?? $stableId,
                    'procID' => $procId,
                    'pipeline' => $procedure['pipelineKey'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $procedure,
            );
        }

        return $results;
    }
}
