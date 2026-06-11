<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ElabftwResponseDto;
use App\ConnectedApps\Shared\ConnectedAppHttpClientInterface;

interface ElabftwHttpClientInterface extends ConnectedAppHttpClientInterface
{
    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): ElabftwResponseDto;

    public function denormalizeResponse(array $content): ElabftwResponseDto;
}
