<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials\Client;

use App\Entity\ConnectedApp;

interface PreclinicalTrialsClientInterface
{
    public function getBaseUrl(ConnectedApp $connectedApp): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function listProtocols(ConnectedApp $connectedApp): array;

    /**
     * @return array<string, mixed>
     */
    public function getProtocol(ConnectedApp $connectedApp, string $protocolId): array;

    public function getViewableProtocolsUrl(ConnectedApp $connectedApp): string;
}
