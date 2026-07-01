<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\BioPortal\Client;

use App\Entity\ConnectedApp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BioPortalClient implements BioPortalClientInterface
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function searchOntologyTerms(
        ConnectedApp $connectedApp,
        string $query,
        int $limit = 20,
        string $ontologies = 'HCMO,NBO,MP,VT',
    ): array {
        $baseUrl = rtrim($connectedApp->getExternalUrl() ?? 'https://data.bioontology.org', '/');
        $token = $connectedApp->getToken();

        $response = $this->httpClient->request('GET', $baseUrl . '/search', [
            'query' => [
                'q' => $query,
                'ontologies' => $ontologies,
                'pagesize' => $limit,
                'include' => 'prefLabel,definition',
                'page' => 1,
            ],
            'headers' => [
                'Authorization' => 'apikey token=' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray();

        return [
            'collection' => $data['collection'] ?? [],
            'totalCount' => $data['totalCount'] ?? 0,
        ];
    }
}
