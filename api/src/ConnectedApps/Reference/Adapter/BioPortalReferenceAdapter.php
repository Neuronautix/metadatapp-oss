<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\BioPortal\Client\BioPortalClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

final readonly class BioPortalReferenceAdapter implements ReferenceSearchAdapterInterface
{
    public function __construct(private BioPortalClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::BioPortal === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $payload = $this->client->searchOntologyTerms($connectedApp, $query, $limit);
        $results = [];

        foreach ($payload['collection'] as $hit) {
            $iri = (string) ($hit['@id'] ?? '');
            if ('' === $iri) {
                continue;
            }

            $label = (string) ($hit['prefLabel'] ?? basename($iri));
            $definition = null;
            if (isset($hit['definition']) && \is_array($hit['definition']) && [] !== $hit['definition']) {
                $definition = (string) $hit['definition'][0];
            }

            // Derive ontology acronym from the links.ontology URL (e.g. ".../HCMO" → "HCMO")
            $ontologyLink = (string) ($hit['links']['ontology'] ?? '');
            $acronym = '' !== $ontologyLink ? basename($ontologyLink) : null;

            // BioPortal class browser URL
            $viewUrl = null !== $acronym
                ? 'https://bioportal.bioontology.org/ontologies/' . $acronym . '?p=classes&conceptid=' . rawurlencode($iri)
                : null;

            $results[] = new ReferenceResult(
                id: 'bioportal:ontology_class:' . rawurlencode(basename($iri)),
                source: AppCode::BioPortal->value,
                sourceName: $connectedApp->getName(),
                type: 'ontology_class',
                title: $label,
                subtitle: $acronym,
                description: $definition,
                externalUrl: $viewUrl,
                identifiers: array_filter([
                    'iri' => $iri,
                    'ontology' => $acronym,
                ], static fn ($v): bool => null !== $v),
                raw: $hit,
            );
        }

        return $results;
    }
}
