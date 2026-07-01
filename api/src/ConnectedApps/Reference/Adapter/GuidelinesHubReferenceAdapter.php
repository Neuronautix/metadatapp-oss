<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\GuidelinesHub\GuidelinesHubDataProvider;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class GuidelinesHubReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private GuidelinesHubDataProvider $provider)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::GuidelinesHub === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $items = $this->provider->search($query, $limit);
        $results = [];

        foreach ($items as $item) {
            $results[] = new ReferenceResult(
                id: 'guidelines_hub:guideline:' . $item['id'],
                source: AppCode::GuidelinesHub->value,
                sourceName: $connectedApp->getName(),
                type: 'guideline',
                title: $item['title'],
                subtitle: $item['sourceLabel'],
                description: $item['description'],
                externalUrl: $item['url'],
                identifiers: ['guideline_id' => $item['id'], 'source' => $item['source']],
                raw: $item,
            );
        }

        return $results;
    }
}
