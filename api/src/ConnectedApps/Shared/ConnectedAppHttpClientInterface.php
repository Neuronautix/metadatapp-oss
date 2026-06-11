<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

interface ConnectedAppHttpClientInterface
{
    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): mixed;

    public function denormalizeResponse(array $content): mixed;
}
