<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Mnms\Client\MnmsClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class MnmsReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private MnmsClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::Mnms === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $fields = $this->client->searchFields($connectedApp, $query, $limit);
        $results = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $results[] = new ReferenceResult(
                id: 'mnms:schema:' . $name,
                source: AppCode::Mnms->value,
                sourceName: $connectedApp->getName(),
                type: 'schema',
                title: $name,
                subtitle: isset($field['unit']) ? 'Unit: ' . $field['unit'] : null,
                description: $field['description'],
                externalUrl: 'https://bids-specification.readthedocs.io/en/stable/',
                identifiers: array_filter([
                    'field' => $name,
                    'datatype' => $field['type'],
                    'unit' => $field['unit'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $field,
            );
        }

        return $results;
    }
}
