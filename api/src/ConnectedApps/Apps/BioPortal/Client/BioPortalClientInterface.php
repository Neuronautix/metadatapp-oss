<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\BioPortal\Client;

use App\Entity\ConnectedApp;

interface BioPortalClientInterface
{
    /**
     * Search ontology classes via BioPortal REST API.
     * Hits `GET /search?q={query}&ontologies={ontologies}&pagesize={limit}`.
     *
     * @return array{collection: list<array<string, mixed>>, totalCount: int}
     */
    public function searchOntologyTerms(
        ConnectedApp $connectedApp,
        string $query,
        int $limit = 20,
        string $ontologies = 'HCMO,NBO,MP,VT',
    ): array;
}
