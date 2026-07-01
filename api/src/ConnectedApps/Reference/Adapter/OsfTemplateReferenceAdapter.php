<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Osf\Client\OsfClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class OsfTemplateReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private OsfClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::Osf === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $schemas = $this->client->listRegistrationSchemas($connectedApp);
        $needle = strtolower($query);
        $results = [];

        foreach ($schemas as $schema) {
            if (\count($results) >= $limit) {
                break;
            }
            $attrs = $schema['attributes'] ?? [];
            $title = (string) ($attrs['title'] ?? $attrs['name'] ?? $schema['id'] ?? '');
            $description = (string) ($attrs['description'] ?? '');

            if ('' !== $needle && !str_contains(strtolower($title . ' ' . $description), $needle)) {
                continue;
            }

            $schemaId = (string) ($schema['id'] ?? '');
            $results[] = new ReferenceResult(
                id: 'osf:template:' . $schemaId,
                source: AppCode::Osf->value,
                sourceName: $connectedApp->getName(),
                type: 'template',
                title: $title ?: $schemaId,
                subtitle: isset($attrs['schema_version']) ? 'v' . $attrs['schema_version'] : null,
                description: '' !== $description ? $description : null,
                externalUrl: 'https://osf.io/registries/' . rawurlencode($schemaId),
                identifiers: array_filter([
                    'schema_id' => $schemaId,
                    'active' => $attrs['active'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $schema,
            );
        }

        return $results;
    }
}
