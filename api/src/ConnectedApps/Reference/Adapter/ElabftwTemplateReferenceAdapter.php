<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class ElabftwTemplateReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private ElabftwHttpClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::Elabftw === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $results = [];
        $needle = strtolower($query);

        foreach ($this->fetchTemplates($connectedApp) as [$kind, $item]) {
            if (\count($results) >= $limit) {
                break;
            }
            $title = (string) ($item['title'] ?? '');
            if ('' !== $needle && !str_contains(strtolower($title), $needle)) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            $idStr = 'elabftw:' . $kind . ':' . $id;
            $body = strip_tags((string) ($item['body'] ?? ''));
            $results[] = new ReferenceResult(
                id: $idStr,
                source: AppCode::Elabftw->value,
                sourceName: $connectedApp->getName(),
                type: 'template',
                title: $title ?: $idStr,
                subtitle: 'template' === $kind ? 'Experiment template' : 'Item type',
                description: '' !== $body ? mb_substr($body, 0, 300) : null,
                externalUrl: null,
                identifiers: ['elabftw_id' => $id, 'kind' => $kind],
                raw: $item,
            );
        }

        return $results;
    }

    /**
     * @return iterable<array{string, array<string, mixed>}>
     */
    private function fetchTemplates(ConnectedApp $connectedApp): iterable
    {
        foreach ($this->client->listExperimentsTemplates($connectedApp) as $item) {
            yield ['template', $item];
        }
        foreach ($this->client->listItemsTypes($connectedApp) as $item) {
            yield ['items_type', $item];
        }
    }
}
