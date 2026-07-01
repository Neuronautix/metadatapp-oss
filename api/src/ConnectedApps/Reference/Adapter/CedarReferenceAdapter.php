<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Cedar\Client\CedarClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class CedarReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private CedarClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::Cedar === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $payload = $this->client->searchArtifacts($connectedApp, $query, $limit);
        $results = [];

        foreach ($payload['resources'] as $resource) {
            $iri = $resource['@id'] ?? null;
            if (null === $iri) {
                continue;
            }

            // Extract UUID from the IRI (last path segment)
            $uuid = basename((string) $iri);

            $results[] = new ReferenceResult(
                id: 'cedar:schema:' . $uuid,
                source: AppCode::Cedar->value,
                sourceName: $connectedApp->getName(),
                type: 'schema',
                title: $resource['schema:name'] ?? $resource['name'] ?? $uuid,
                subtitle: $resource['resourceType'] ?? null,
                description: $resource['schema:description'] ?? null,
                externalUrl: 'https://cedar.metadatacenter.org/tools/template-editor/' . rawurlencode($uuid),
                identifiers: array_filter([
                    'iri' => $iri,
                    'version' => $resource['pav:version'] ?? null,
                    'status' => $resource['bibo:status'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $resource,
            );
        }

        return $results;
    }
}
