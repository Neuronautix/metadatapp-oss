<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Shared\ConnectedAppHttpClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface SoftMouseHttpClientInterface extends ConnectedAppHttpClientInterface
{
    public function getSoftMouseClient(): HttpClientInterface;

    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): SoftMouseResponseDto;

    public function denormalizeResponse(array $content): SoftMouseResponseDto;
}
