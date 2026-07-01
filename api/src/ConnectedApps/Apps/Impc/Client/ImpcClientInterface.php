<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Impc\Client;

use App\Entity\ConnectedApp;

interface ImpcClientInterface
{
    public function getBaseUrl(ConnectedApp $connectedApp): string;

    /**
     * List the IMPReSS phenotyping pipelines (the top of the standardized-screen hierarchy).
     *
     * @return array{totalItems: int, pipelines: list<array<string, mixed>>}
     */
    public function listPipelines(ConnectedApp $connectedApp, int $limit = 20, int $offset = 0, bool $useCache = true): array;

    /**
     * Search IMPReSS standardized procedures (e.g. Open Field, Acoustic Startle, Grip Strength).
     *
     * @return array{totalItems: int, procedures: list<array<string, mixed>>}
     */
    public function searchProcedures(ConnectedApp $connectedApp, string $searchTerm, int $limit = 20, int $offset = 0, bool $useCache = true): array;

    /**
     * Fetch a single IMPReSS procedure by its upstream numeric id, including its
     * standardized parameters (stitched in from the dedicated parameter endpoint).
     *
     * @return array<string, mixed>
     */
    public function getProcedure(ConnectedApp $connectedApp, string $procedureId): array;
}
