<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AiControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function itReportsProviderStatusWhenTheMockGatewayIsEnabled(): void
    {
        $this->configureAssistantEnvironment(true, 'mock');

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/ai/status');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertTrue($payload['status']['enabled']);
        $this->assertTrue($payload['status']['available']);
        $this->assertSame('mock', $payload['status']['provider']);
        $this->assertSame('status', $payload['trace']['action']);
        $this->assertSame('assistant_status', $payload['trace']['taskType']);
    }

    #[Test]
    public function itHandlesChatRequestsWithGovernanceTraceMetadata(): void
    {
        $this->configureAssistantEnvironment(true, 'mock');

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/ai/chat', [
            'json' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Can you help me draft search filters?'],
                ],
                'context' => [
                    'resourceType' => 'subject',
                    'resourceId' => 'list',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertStringContainsString('search filters', strtolower($payload['reply']));
        $this->assertSame('chat', $payload['trace']['action']);
        $this->assertSame('/ai/chat', $payload['trace']['route']);
        $this->assertSame('assistant_chat', $payload['trace']['taskType']);
        $this->assertSame('mock', $payload['status']['provider']);
    }

    #[Test]
    public function itIncludesContractCompatibleMcpProvenanceWhenBridgeIsEnabled(): void
    {
        $this->configureAssistantEnvironment(true, 'mock', true);

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/ai/chat', [
            'json' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Show the latest sensor reading'],
                ],
                'context' => [],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertNotEmpty($payload['provenance']);

        $firstProvenanceEntry = $payload['provenance'][0];
        $this->assertSame('MCP tool call', $firstProvenanceEntry['label']);
        $this->assertSame('get_live_sensor_observation', $firstProvenanceEntry['value']);
        $this->assertStringStartsWith('mcp:', (string) $firstProvenanceEntry['source']);
        $this->assertSame('get_live_sensor_observation', $firstProvenanceEntry['tool']);
        $this->assertArrayHasKey('detail', $firstProvenanceEntry);
        $this->assertArrayHasKey('callDurationMs', $firstProvenanceEntry);
    }

    #[Test]
    public function itReturnsReviewableSuggestionPayloads(): void
    {
        $this->configureAssistantEnvironment(true, 'mock');

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/ai/suggest', [
            'json' => [
                'action' => 'draft_query_filters',
                'contextType' => 'subjects_list',
                'prompt' => 'Show restricted rat subjects',
                'context' => [
                    'resourceType' => 'subject',
                    'resourceId' => 'list',
                    'filters' => [
                        'search' => '',
                        'species' => '',
                        'consent' => '',
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('draft_query_filters', $payload['action']);
        $this->assertSame('rat', $payload['structuredOutput']['filters']['species']);
        $this->assertSame('restricted', $payload['structuredOutput']['filters']['consent']);
        $this->assertTrue($payload['reviewRequired']);
        $this->assertFalse($payload['canApplyDirectly']);
        $this->assertNotEmpty($payload['suggestions']);
        $this->assertSame('suggest', $payload['trace']['action']);
    }

    #[Test]
    public function itFailsClosedOnStatusWhenTheAssistantIsDisabled(): void
    {
        $this->configureAssistantEnvironment(false, 'null');

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/ai/status');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertFalse($payload['status']['enabled']);
        $this->assertFalse($payload['status']['available']);
        $this->assertSame('null', $payload['status']['provider']);
    }

    #[Test]
    public function itFailsClosedOnChatWhenTheAssistantIsDisabled(): void
    {
        $this->configureAssistantEnvironment(false, 'null');

        $client = $this->authenticatedClient();
        $client->request('POST', '/ai/chat', [
            'json' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'hello'],
                ],
                'context' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(503);
    }

    #[Test]
    public function itSavesLlmProviderCredentialsWithoutReturningRawKeys(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = $this->authenticatedClientForUser($user);
        $response = $client->request('PATCH', '/ai/providers/openai', [
            'json' => [
                'apiKey' => 'sk-test-openai-secret',
                'model' => 'gpt-5',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('openai', $payload['provider']);
        $this->assertSame('gpt-5', $payload['model']);
        $this->assertTrue($payload['configured']);
        $this->assertSame('user', $payload['source']);
        $this->assertStringContainsString('sk-t', $payload['apiKeyHint']);
        $this->assertStringNotContainsString('openai-secret', json_encode($payload, \JSON_THROW_ON_ERROR));

        $listClient = $this->authenticatedClientForUser($user);
        $listResponse = $listClient->request('GET', '/ai/providers');
        $this->assertResponseIsSuccessful();
        $listPayload = $listResponse->toArray();
        $this->assertStringNotContainsString('openai-secret', json_encode($listPayload, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itKeepsExistingLlmProviderKeyWhenOnlyModelChanges(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = $this->authenticatedClientForUser($user);
        $client->request('PATCH', '/ai/providers/anthropic', [
            'json' => [
                'apiKey' => 'sk-ant-secret-value',
                'model' => 'claude-opus-4-1-20250805',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $modelUpdateClient = $this->authenticatedClientForUser($user);
        $response = $modelUpdateClient->request('PATCH', '/ai/providers/anthropic', [
            'json' => [
                'model' => 'claude-sonnet-4-20250514',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('anthropic', $payload['provider']);
        $this->assertSame('claude-sonnet-4-20250514', $payload['model']);
        $this->assertTrue($payload['configured']);
        $this->assertNotEmpty($payload['apiKeyHint']);
    }

    private function authenticatedClient(): Client
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        return $this->authenticatedClientForUser($user);
    }

    private function authenticatedClientForUser(User $user): Client
    {
        $client = static::createClient();
        $client->loginUser($user);

        return $client;
    }

    private function configureAssistantEnvironment(bool $enabled, string $provider, bool $mcpBridgeEnabled = false): void
    {
        putenv(sprintf('AI_ASSISTANT_ENABLED=%s', $enabled ? '1' : '0'));
        putenv(sprintf('AI_MODEL_PROVIDER=%s', $provider));
        putenv(sprintf('AI_MCP_BRIDGE_ENABLED=%s', $mcpBridgeEnabled ? '1' : '0'));

        $_ENV['AI_ASSISTANT_ENABLED'] = $enabled ? '1' : '0';
        $_ENV['AI_MODEL_PROVIDER'] = $provider;
        $_ENV['AI_MCP_BRIDGE_ENABLED'] = $mcpBridgeEnabled ? '1' : '0';
        $_SERVER['AI_ASSISTANT_ENABLED'] = $enabled ? '1' : '0';
        $_SERVER['AI_MODEL_PROVIDER'] = $provider;
        $_SERVER['AI_MCP_BRIDGE_ENABLED'] = $mcpBridgeEnabled ? '1' : '0';

        self::ensureKernelShutdown();
    }
}
