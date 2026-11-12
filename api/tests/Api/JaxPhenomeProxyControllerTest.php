<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\JaxPhenome\Client\JaxPhenomeClientInterface;
use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Controller\Api\JaxPhenomeProxyController;
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
class JaxPhenomeProxyControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private function createController(JaxPhenomeClientInterface $client): JaxPhenomeProxyController
    {
        return new JaxPhenomeProxyController(
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

    private function createJaxPhenomeConnectedApp(): object
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        return ConnectedAppFactory::createOne([
            'code' => AppCode::JaxPhenome,
            'token' => null,
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://phenome.jax.org',
        ]);
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/connected_apps/test-id/jax-phenome/test-connection');

        $this->assertSame('api_jax_phenome_test_connection', $route['_route']);
        $this->assertSame('test-id', $route['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function getMeasureEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/connected_apps/test-id/jax-phenome/measures/12345');

        $this->assertSame('api_jax_phenome_get_measure', $route['_route']);
        $this->assertSame('test-id', $route['id']);
        $this->assertSame('12345', $route['measureId']);
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionReturnsStrainsCount(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://phenome.jax.org');
        $client->expects($this->once())
            ->method('listStrains')
            ->with($connectedApp, 1, 0)
            ->willReturn(['totalItems' => 3, 'strains' => [['strainid' => 7], ['strainid' => 14], ['strainid' => 22]]])
        ;

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame('https://phenome.jax.org', $payload['externalUrl']);
        $this->assertSame(3, $payload['strainsCount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function searchMeasuresReturnsMeasures(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->expects($this->once())
            ->method('searchMeasures')
            ->with($connectedApp, 'body weight', 20, 0)
            ->willReturn(['totalItems' => 1, 'measures' => [['measnum' => 12345, 'description' => 'Body weight at 6 weeks of age']]])
        ;

        $request = new Request(['searchTerm' => 'body weight']);
        $response = $this->createController($client)->searchMeasures($connectedApp, $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $payload['totalItems']);
        $this->assertCount(1, $payload['measures']);
        $this->assertSame(12345, $payload['measures'][0]['measnum']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function getMeasureReturnsRawMeasure(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->expects($this->once())
            ->method('getMeasure')
            ->with($connectedApp, '12345')
            ->willReturn(['measnum' => 12345, 'varname' => 'bw_6wk', 'units' => 'g'])
        ;

        $response = $this->createController($client)->getMeasure($connectedApp, '12345');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(12345, $payload['measnum']);
        $this->assertSame('bw_6wk', $payload['varname']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function listStrainsReturnsStrains(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->expects($this->once())
            ->method('listStrains')
            ->with($connectedApp, 20, 0)
            ->willReturn(['totalItems' => 2, 'strains' => [['strainid' => 7], ['strainid' => 14]]])
        ;

        $response = $this->createController($client)->listStrains($connectedApp, new Request());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(2, $payload['totalItems']);
        $this->assertCount(2, $payload['strains']);
    }

    /**
     * @test
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function connectionReturns502WhenUpstreamFails(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://phenome.jax.org');
        $client->expects($this->once())
            ->method('listStrains')
            ->willThrowException(new \RuntimeException('connection refused'))
        ;

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate JAX Phenome connection. Check URL and API availability.', $payload['message']);
        $this->assertSame('connection refused', $payload['detail']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wrongAppCodeIsRejected(): void
    {
        // A connected app that is not JAX Phenome must be refused even when the
        // account/user guard passes (here the super-admin bypass is active).
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $bioPortalApp = ConnectedAppFactory::createOne([
            'code' => AppCode::BioPortal,
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://data.bioontology.org',
        ]);

        $client = $this->createMock(JaxPhenomeClientInterface::class);
        $client->expects($this->never())->method('listStrains');

        $response = $this->createController($client)->testConnection($bioPortalApp);

        // assertValidConnectedApp throws AccessDeniedException, caught and surfaced as 502.
        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('App is not JAX Phenome.', $payload['detail']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function strainsEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/jax-phenome/strains');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        // MPD has no list-all endpoint; the client resolves a curated default set.
        $this->assertSame(12, $payload['totalItems']);
        $this->assertNotEmpty($payload['strains']);
        $this->assertSame('C57BL/6J', $payload['strains'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function measuresEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/jax-phenome/measures?searchTerm=body+weight');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame(2, $payload['totalItems']);
        $this->assertCount(2, $payload['measures']);
        $this->assertSame(12345, $payload['measures'][0]['measnum']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function measureDetailEndpointReturnsDeterministicMockDataForOwningAccount(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();
        $owner = $connectedApp->getUser();

        $httpClient = static::createClient();
        $httpClient->loginUser($owner);

        $response = $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/jax-phenome/measures/12345');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame(12345, $payload['measnum']);
        $this->assertSame('bw_6wk', $payload['varname']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userFromAnotherAccountCannotAccessConnectedApp(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        // Force a brand-new, distinct account so this user genuinely belongs to a
        // different tenant than the connected app under test.
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $httpClient = static::createClient();
        $httpClient->loginUser($otherUser);

        // Tenant isolation: the account guard throws AccessDeniedException for a
        // foreign account. The proxy controller catches it (like NIH CDE / Fair3R)
        // and surfaces it as a 502 without ever revealing the upstream data.
        $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/jax-phenome/strains');

        $this->assertResponseStatusCodeSame(502);
        $payload = json_decode((string) $httpClient->getKernelBrowser()->getResponse()->getContent(), true);
        $this->assertSame('Connected app does not belong to the current account.', $payload['detail']);
        $this->assertArrayNotHasKey('strains', $payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticatedUserIsDenied(): void
    {
        $connectedApp = $this->createJaxPhenomeConnectedApp();

        $httpClient = static::createClient();
        $httpClient->request('GET', '/connected_apps/' . $connectedApp->getId() . '/jax-phenome/strains');

        $this->assertContains($httpClient->getResponse()->getStatusCode(), [401, 403]);
    }
}
