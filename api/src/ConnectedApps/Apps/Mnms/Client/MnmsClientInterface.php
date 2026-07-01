<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Mnms\Client;

use App\Entity\ConnectedApp;

interface MnmsClientInterface
{
    /**
     * Search MNMS metadata field definitions by query.
     *
     * @return list<array{name: string, description: string, type: string, unit?: string|null}>
     */
    public function searchFields(ConnectedApp $connectedApp, string $query, int $limit): array;
}
