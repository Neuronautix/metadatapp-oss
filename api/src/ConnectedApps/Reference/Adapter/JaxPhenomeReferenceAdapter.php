<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\JaxPhenome\Client\JaxPhenomeClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

/**
 * Maps JAX Phenome (MPD) measures (resolved via ontology terms) and strains
 * (curated default set, filtered by query) into reference results.
 */
final readonly class JaxPhenomeReferenceAdapter implements ReferenceSearchAdapterInterface
{
    private const string PUBLIC_SITE = 'https://phenome.jax.org';

    public function __construct(private JaxPhenomeClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::JaxPhenome === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $site = $this->publicSite($connectedApp);
        $results = [];

        $measures = $this->client->searchMeasures($connectedApp, $query, $limit, 0);
        foreach ($measures['measures'] as $measure) {
            $measnum = $measure['measnum'] ?? $measure['id'] ?? null;
            if (null === $measnum) {
                continue;
            }
            $results[] = new ReferenceResult(
                id: 'jax_phenome:measure:' . $measnum,
                source: AppCode::JaxPhenome->value,
                sourceName: $connectedApp->getName(),
                type: 'measure',
                title: \is_string($measure['name'] ?? null) ? $measure['name'] : ('Measure ' . $measnum),
                subtitle: \is_string($measure['units'] ?? null) ? $measure['units'] : null,
                description: \is_string($measure['description'] ?? null) ? $measure['description'] : null,
                externalUrl: $site . '/measureset/' . rawurlencode((string) $measnum),
                identifiers: array_filter([
                    'measnum' => $measnum,
                    'projsym' => $measure['projectsym'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $measure,
            );
        }

        $needle = mb_strtolower($query);
        $strains = $this->client->listStrains($connectedApp, 30, 0);
        foreach ($strains['strains'] as $strain) {
            $name = (string) ($strain['name'] ?? $strain['symbol'] ?? '');
            $symbol = (string) ($strain['symbol'] ?? '');
            if (!str_contains(mb_strtolower($name), $needle) && !str_contains(mb_strtolower($symbol), $needle)) {
                continue;
            }
            $strainId = $strain['id'] ?? $strain['strainid'] ?? null;
            $results[] = new ReferenceResult(
                id: 'jax_phenome:strain:' . ($strainId ?? $name),
                source: AppCode::JaxPhenome->value,
                sourceName: $connectedApp->getName(),
                type: 'strain',
                title: '' !== $name ? $name : (string) $strainId,
                subtitle: '' !== $symbol ? $symbol : null,
                description: null,
                externalUrl: null !== $strainId ? $site . '/strain/' . rawurlencode((string) $strainId) : null,
                identifiers: array_filter(['strainid' => $strainId, 'stocknum' => $strain['stocknum'] ?? null], static fn ($v): bool => null !== $v),
                raw: $strain,
            );
        }

        return $results;
    }

    private function publicSite(ConnectedApp $connectedApp): string
    {
        $url = $connectedApp->getExternalUrl();

        return null !== $url && '' !== trim($url) ? rtrim(trim($url), '/') : self::PUBLIC_SITE;
    }
}
