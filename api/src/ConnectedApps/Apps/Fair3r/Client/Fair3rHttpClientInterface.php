<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\Fair3rResponseDto;
use App\ConnectedApps\Shared\ConnectedAppHttpClientInterface;

/**
 * Interface for HTTP client used to communicate with Fair3R.
 */
interface Fair3rHttpClientInterface extends ConnectedAppHttpClientInterface
{
    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): Fair3rResponseDto;

    public function denormalizeResponse(array $content): Fair3rResponseDto;
}
