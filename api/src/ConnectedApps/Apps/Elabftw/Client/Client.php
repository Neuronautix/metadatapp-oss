<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client;

use App\ConnectedApps\Apps\Elabftw\Client\Resources\Experiments;
use App\ConnectedApps\Apps\Elabftw\Client\Resources\Items;
use App\ConnectedApps\Apps\Elabftw\Client\Resources\Users;
use App\Entity\ConnectedApp;

/**
 * API client for Elabftw resources.
 */
readonly class Client
{
    public function __construct(
        private ElabftwHttpClientInterface $httpClient,
    ) {
    }

    public function experiments(?ConnectedApp $connectedApp = null): Experiments
    {
        return new Experiments($this->httpClient, $this->buildRequestOptions($connectedApp));
    }

    public function items(?ConnectedApp $connectedApp = null): Items
    {
        return new Items($this->httpClient, $this->buildRequestOptions($connectedApp));
    }

    public function users(?ConnectedApp $connectedApp = null): Users
    {
        return new Users($this->httpClient, $this->buildRequestOptions($connectedApp));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestOptions(?ConnectedApp $connectedApp): array
    {
        if (!$connectedApp) {
            return [];
        }

        $options = [];
        $externalUrl = $connectedApp->getExternalUrl();
        if (\is_string($externalUrl) && '' !== trim($externalUrl)) {
            $options['base_uri'] = rtrim($externalUrl, '/') . '/api/v2/';
        }

        $token = $connectedApp->getToken();
        if (\is_string($token) && '' !== trim($token)) {
            $options['headers']['Authorization'] = $token;
        }

        return $options;
    }
}
