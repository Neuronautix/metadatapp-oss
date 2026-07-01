<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

/**
 * Maps PreclinicalTrials.eu registered protocols into reference results. The
 * registry has no keyword endpoint, so protocols are listed then filtered by query.
 */
final readonly class PreclinicalTrialsReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private PreclinicalTrialsClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::PreclinicalTrials === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $needle = mb_strtolower($query);
        $results = [];

        foreach ($this->client->listProtocols($connectedApp) as $protocol) {
            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) ($protocol['title'] ?? ''),
                (string) ($protocol['researchField'] ?? ''),
                (string) ($protocol['species'] ?? ''),
                (string) ($protocol['strain'] ?? ''),
            ])));
            if ('' !== $needle && !str_contains($haystack, $needle)) {
                continue;
            }

            $id = $protocol['id'] ?? null;
            if (null === $id) {
                continue;
            }
            $repositoryLink = \is_string($protocol['repositoryLink'] ?? null) && '' !== $protocol['repositoryLink']
                ? $protocol['repositoryLink']
                : null;

            $results[] = new ReferenceResult(
                id: 'preclinicaltrials:protocol:' . $id,
                source: AppCode::PreclinicalTrials->value,
                sourceName: $connectedApp->getName(),
                type: 'protocol',
                title: \is_string($protocol['title'] ?? null) && '' !== $protocol['title'] ? $protocol['title'] : ('Protocol ' . $id),
                subtitle: \is_string($protocol['status'] ?? null) ? $protocol['status'] : null,
                description: $this->summary($protocol),
                externalUrl: $repositoryLink ?? $this->client->getViewableProtocolsUrl($connectedApp),
                identifiers: array_filter([
                    'id' => $id,
                    'species' => $protocol['species'] ?? null,
                    'strain' => $protocol['strain'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $protocol,
            );

            if (\count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function summary(array $protocol): ?string
    {
        $parts = array_filter([
            \is_string($protocol['researchField'] ?? null) ? $protocol['researchField'] : null,
            \is_string($protocol['species'] ?? null) ? $protocol['species'] : null,
            \is_string($protocol['strain'] ?? null) ? $protocol['strain'] : null,
        ]);

        return [] === $parts ? null : implode(' · ', $parts);
    }
}
