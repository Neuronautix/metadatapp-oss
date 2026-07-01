<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\Species;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class McpControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function itReturnsASessionHeaderDuringInitialize(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 'initialize-1',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => [],
                    'clientInfo' => [
                        'name' => 'api-test',
                        'version' => '1.0.0',
                    ],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Mcp-Protocol-Version' => '2025-06-18',
            ],
        ]);

        self::assertContains($response->getStatusCode(), [200, 202]);
        self::assertNotEmpty($response->getHeaders(false)['mcp-session-id'][0] ?? null);
    }

    #[Test]
    public function itListsMouseSubjectsThroughTheMcpTool(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        SubjectFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'MCP-MOUSE-001',
            'species' => Species::Mouse,
        ]);
        SubjectFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'MCP-ZF-001',
            'species' => Species::Zebrafish,
        ]);

        $client = static::createClient();
        $client->disableReboot();
        $client->loginUser($user);

        $sessionId = $this->initializeSession($client);

        $client->loginUser($user);
        $initializedResponse = $client->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
                'params' => [],
            ],
            'headers' => $this->mcpHeaders($sessionId),
        ]);
        self::assertSame(202, $initializedResponse->getStatusCode(), $initializedResponse->getContent(false));

        $client->loginUser($user);
        $response = $client->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 'list-mice-1',
                'method' => 'tools/call',
                'params' => [
                    'name' => 'list_mice',
                    'arguments' => [
                        'species' => 'mouse',
                    ],
                ],
            ],
            'headers' => $this->mcpHeaders($sessionId),
        ]);

        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $payload = $this->decodeMcpResponse($response->getContent(false));

        self::assertArrayNotHasKey('error', $payload, json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertStringContainsString('MCP-MOUSE-001', json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('MCP-ZF-001', json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function authenticatedClient(): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        return $client;
    }

    private function initializeSession(\ApiPlatform\Symfony\Bundle\Test\Client $client): string
    {
        $response = $client->request('POST', '/mcp', [
            'json' => [
                'jsonrpc' => '2.0',
                'id' => 'initialize-1',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => [],
                    'clientInfo' => [
                        'name' => 'api-test',
                        'version' => '1.0.0',
                    ],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
                'Mcp-Protocol-Version' => '2025-06-18',
            ],
        ]);

        self::assertContains($response->getStatusCode(), [200, 202]);

        $sessionId = $response->getHeaders(false)['mcp-session-id'][0] ?? null;
        self::assertNotEmpty($sessionId);

        return $sessionId;
    }

    /**
     * @return array<string, string>
     */
    private function mcpHeaders(string $sessionId): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'Mcp-Protocol-Version' => '2025-06-18',
            'Mcp-Session-Id' => $sessionId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMcpResponse(string $body): array
    {
        if (str_starts_with(trim($body), 'data:')) {
            foreach (preg_split('/\R/', $body) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    return json_decode(trim(substr($line, 5)), true, flags: \JSON_THROW_ON_ERROR);
                }
            }
        }

        return json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
    }
}
