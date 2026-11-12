<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Http;

use App\Mcp\Http\PatchedStreamableHttpTransport;
use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PatchedStreamableHttpTransportTest extends TestCase
{
    #[Test]
    public function itKeepsTheSessionHeaderOnAcceptedResponses(): void
    {
        $psr17Factory = new Psr17Factory();
        $transport = new PatchedStreamableHttpTransport(
            $psr17Factory->createServerRequest('POST', '/mcp'),
            $psr17Factory,
            $psr17Factory,
        );

        $sessionId = Uuid::v4();
        $transport->setSessionId($sessionId);

        $createJsonResponse = new \ReflectionMethod($transport, 'createJsonResponse');
        $createJsonResponse->setAccessible(true);
        $response = $createJsonResponse->invoke($transport);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame($sessionId->toRfc4122(), $response->getHeaderLine('Mcp-Session-Id'));
    }
}
