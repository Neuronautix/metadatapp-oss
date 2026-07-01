<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client;

use App\ConnectedApps\Apps\Fair3r\Client\Resources\Datasets;
use App\ConnectedApps\Apps\Fair3r\Client\Resources\Datastores;

class Client
{
    public function __construct(
        private readonly Fair3rHttpClientInterface $fair3rHttpClient,
    ) {
    }

    public function datasets(): Datasets
    {
        return new Datasets($this->fair3rHttpClient);
    }

    public function datastores(): Datastores
    {
        return new Datastores($this->fair3rHttpClient);
    }

    /**
     * Backward-compatible alias.
     */
    public function datastore(): Datastores
    {
        return $this->datastores();
    }
}
