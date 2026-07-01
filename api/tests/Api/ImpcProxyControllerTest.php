<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\Impc\Client\ImpcClientInterface;
use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Controller\Api\ImpcProxyController;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\AppCode;
use App\Security\Roles;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImpcProxyControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private function createController(ImpcClientInterface $client): ImpcProxyController
    {
        return new ImpcProxyController(
            $client,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            new ConnectedAppRateLimiter(new ArrayAdapter(), new MockClock()),
        );
    }

    private function createSecurityBypass(): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        return $security;
    }

    private function createImpcConnectedApp(): object
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        return ConnectedAppFactory::createOne([
            'code' => AppCode::Impc,
            'token' => null,
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://api.mousephenotype.org',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/connected_apps/test-id/impc/test-connection');

        $this->assertSame('api_impc_test_connection', $route['_route']);
        $this->assertSame('test-id', $route['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function getProcedureEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/connected_apps/test-id/impc/procedures/499');

        $this->assertSame('api_impc_get_procedure', $route['_route']);
        $this->assertSame('test-id', $route['id']);
        $this->assertSame('499', $route['procedureId']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionReturnsPipelinesCount(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $client = $this->createMock(ImpcClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://api.mousephenotype.org');
        $client->expects($this->once())
            ->method('listPipelines')
            ->with($connectedApp, 1, 0, false)
            ->willReturn(['totalItems' => 2, 'pipelines' => [['stableKey' => 'IMPC_001'], ['stableKey' => 'IMPC_002']]])
        ;

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame('https://api.mousephenotype.org', $payload['externalUrl']);
        $this->assertSame(2, $payload['pipelinesCount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function searchProceduresReturnsProcedures(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $client = $this->createMock(ImpcClientInterface::class);
        $client->expects($this->once())
            ->method('searchProcedures')
            ->with($connectedApp, 'open field', 20, 0)
            ->willReturn(['totalItems' => 1, 'procedures' => [['stableKey' => 'IMPC_OFD_001', 'name' => 'Open Field']]])
        ;

        $request = new Request(['searchTerm' => 'open field']);
        $response = $this->createController($client)->searchProcedures($connectedApp, $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $payload['totalItems']);
        $this->assertCount(1, $payload['procedures']);
        $this->assertSame('IMPC_OFD_001', $payload['procedures'][0]['stableKey']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function getProcedureReturnsRawProcedure(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $client = $this->createMock(ImpcClientInterface::class);
        $client->expects($this->once())
            ->method('getProcedure')
            ->with($connectedApp, '499')
            ->willReturn(['procedureId' => 499, 'stableKey' => 'IMPC_OFD_001', 'name' => 'Open Field', 'parameters' => [['stableKey' => 'IMPC_OFD_009_001']]])
        ;

        $response = $this->createController($client)->getProcedure($connectedApp, '499');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('IMPC_OFD_001', $payload['stableKey']);
        $this->assertCount(1, $payload['parameters']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function listPipelinesReturnsPipelines(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $client = $this->createMock(ImpcClientInterface::class);
        $client->expects($this->once())
            ->method('listPipelines')
            ->with($connectedApp, 20, 0)
            ->willReturn(['totalItems' => 2, 'pipelines' => [['stableKey' => 'IMPC_001'], ['stableKey' => 'IMPC_002']]])
        ;

        $response = $this->createController($client)->listPipelines($connectedApp, new Request());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(2, $payload['totalItems']);
        $this->assertCount(2, $payload['pipelines']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionReturns502WhenUpstreamFails(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $client = $this->createMock(ImpcClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://api.mousephenotype.org');
        $client->expects($this->once())
            ->method('listPipelines')
            ->willThrowException(new \RuntimeException('connection refused'))
        ;

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate IMPReSS connection. Check URL and API availability.', $payload['message']);
        $this->assertSame('connection refused', $payload['detail']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wrongAppCodeIsRejected(): void
    {
        // A connected app that is not IMPReSS must be refused even when the
        // account/user guard passes (here the super-admin bypass is active).
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $bioPortalApp = ConnectedAppFactory::createOne([
            'code' => AppCode::BioPortal,
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://data.bioontology.org',
        ]);

        $client = $this->createMock(ImpcClientInterface::class);
        $client->expects($this->never())->method('listPipelines');

        $response = $this->createController($client)->testConnection($bioPortalApp);

        // assertValidConnectedApp throws AccessDeniedException, caught and surfaced as 502.
        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('App is not IMPReSS.', $payload['detail']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function proceduresEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createImpcConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/impc/procedures?searchTerm=open+field');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame(1, $payload['totalItems']);
        $this->assertCount(1, $payload['procedures']);
        $this->assertSame('Open Field', $payload['procedures'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function procedureDetailEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createImpcConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        // Detail is keyed by the procedure's stable id; the client assembles the
        // procedure and its parameters from the IMPC Solr documents.
        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/impc/procedures/IMPC_OFD_001');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('IMPC_OFD_001', $payload['stableKey']);
        $this->assertNotEmpty($payload['parameters']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pipelinesEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createImpcConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/impc/pipelines');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame(2, $payload['totalItems']);
        $this->assertCount(2, $payload['pipelines']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userFromAnotherAccountCannotAccessConnectedApp(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        // Force a brand-new, distinct account so this user genuinely belongs to a
        // different tenant than the connected app under test.
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $httpClient = static::createClient();
        $httpClient->loginUser($otherUser);

        // Tenant isolation: the account guard throws AccessDeniedException for a
        // foreign account. The proxy controller catches it and surfaces it as a
        // 502 without ever revealing the upstream data.
        $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/impc/pipelines');

        $this->assertResponseStatusCodeSame(502);
        $payload = json_decode((string) $httpClient->getKernelBrowser()->getResponse()->getContent(), true);
        $this->assertSame('Connected app does not belong to the current account.', $payload['detail']);
        $this->assertArrayNotHasKey('pipelines', $payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticatedUserIsDenied(): void
    {
        $connectedApp = $this->createImpcConnectedApp();

        $httpClient = static::createClient();
        $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/impc/pipelines');

        $this->assertContains($httpClient->getResponse()->getStatusCode(), [401, 403]);
    }
}
