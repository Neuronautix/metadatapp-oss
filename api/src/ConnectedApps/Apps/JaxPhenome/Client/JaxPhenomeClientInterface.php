<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\JaxPhenome\Client;

use App\Entity\ConnectedApp;

interface JaxPhenomeClientInterface
{
    public function getBaseUrl(ConnectedApp $connectedApp): string;

    /**
     * @return array{totalItems: int, strains: list<array<string, mixed>>}
     */
    public function listStrains(ConnectedApp $connectedApp, int $limit = 20, int $offset = 0, bool $useCache = true): array;

    /**
     * @return array{totalItems: int, measures: list<array<string, mixed>>, ontologyTerms: list<array{id: string, descrip: string}>, resolvedTerm: array<string, mixed>|null}
     */
    public function searchMeasures(ConnectedApp $connectedApp, string $searchTerm, int $limit = 20, int $offset = 0, bool $useCache = true): array;

    /**
     * @return array<string, mixed>
     */
    public function getMeasure(ConnectedApp $connectedApp, string $measureId): array;

    /**
     * Resolve free text to MPD ontology terms (used to drive measure search).
     *
     * @return list<array{id: string, descrip: string}>
     */
    public function suggestOntologyTerms(ConnectedApp $connectedApp, string $searchTerm, int $limit = 10, bool $useCache = true): array;

    /**
     * Fetch a single strain's detail by nomenclature.
     *
     * @return array<string, mixed>
     */
    public function lookupStrain(ConnectedApp $connectedApp, string $name): array;
}
