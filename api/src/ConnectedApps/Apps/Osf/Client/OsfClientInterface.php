<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Osf\Client;

use App\Entity\ConnectedApp;

interface OsfClientInterface
{
    /** @return array<string, mixed> */
    public function getCurrentUser(ConnectedApp $connectedApp): array;

    /** @return list<array<string, mixed>> */
    public function listProjects(ConnectedApp $connectedApp): array;

    /** @return list<array<string, mixed>> */
    public function listRegistrationSchemas(ConnectedApp $connectedApp): array;

    public function getBaseApiUrl(ConnectedApp $connectedApp): string;
}
