<?php

declare(strict_types=1);

namespace App\Mcp\Http;

use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;

final class PatchedStreamableHttpTransport extends StreamableHttpTransport
{
    protected function createJsonResponse(): ResponseInterface
    {
        $response = parent::createJsonResponse();

        if (202 !== $response->getStatusCode() || null === $this->sessionId) {
            return $response;
        }

        return $response->withHeader('Mcp-Session-Id', $this->sessionId->toRfc4122());
    }
}
