<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClient;
use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClientInterface;
use App\ConnectedApps\Apps\PreclinicalTrials\PreclinicalTrialsServiceInterface;
use App\Controller\Api\PreclinicalTrialsProxyController;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PreclinicalTrialsProxyControllerTest extends ApiTestCase
{
    private const string PUBLIC_TOKEN = 'LNRV4rINjSOWUo8uNAw9vDnjH8xTc0Gg';

    private function createController(PreclinicalTrialsClientInterface $client, ?PreclinicalTrialsServiceInterface $service = null): PreclinicalTrialsProxyController
    {
        return new PreclinicalTrialsProxyController(
            $client,
            $service ?? $this->createMock(PreclinicalTrialsServiceInterface::class),
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createSecurityBypass(): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        return $security;
    }

    private function createConnectedApp(
        string $externalUrl = 'https://preclinicaltrials.eu/api',
        string $token = self::PUBLIC_TOKEN,
    ): ConnectedApp {
        $app = $this->createMock(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::v4());
        $app->method('getCode')->willReturn(AppCode::PreclinicalTrials);
        $app->method('getExternalUrl')->willReturn($externalUrl);
        $app->method('getToken')->willReturn($token);

        return $app;
    }

    #[Test]
    public function testConnectionEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('POST');
        $route = $router->match('/connected_apps/test-id/preclinicaltrials/test-connection');

        $this->assertSame('api_preclinicaltrials_test_connection', $route['_route']);
        $this->assertSame('test-id', $route['id']);
    }

    #[Test]
    public function testConnectionReturnsProtocolCountOnSuccess(): void
    {
        $connectedApp = $this->createConnectedApp();

        $protocols = [
            ['pct_id' => 'PCT0000001', 'short_title' => 'Protocol A'],
            ['pct_id' => 'PCT0000002', 'short_title' => 'Protocol B'],
        ];

        $client = $this->createMock(PreclinicalTrialsClientInterface::class);
        $client->method('listProtocols')->with($connectedApp)->willReturn($protocols);
        $client->method('getViewableProtocolsUrl')->with($connectedApp)->willReturn('https://preclinicaltrials.eu/api/external/viewable-protocols');

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame(2, $payload['protocolsCount']);
        $this->assertSame('https://preclinicaltrials.eu/api/external/viewable-protocols', $payload['externalUrl']);
    }

    #[Test]
    public function testClientNormalizesPreclinicalTrialsWwwHost(): void
    {
        $connectedApp = $this->createConnectedApp('https://www.preclinicaltrials.eu/api/external/viewable-protocols');
        $client = new PreclinicalTrialsClient($this->createMock(HttpClientInterface::class));

        $this->assertSame('https://preclinicaltrials.eu/api/external/viewable-protocols', $client->getViewableProtocolsUrl($connectedApp));
    }

    #[Test]
    public function testConnectionReturns502WhenUpstreamFails(): void
    {
        $connectedApp = $this->createConnectedApp();

        $client = $this->createMock(PreclinicalTrialsClientInterface::class);
        $client->method('listProtocols')->willThrowException(new \RuntimeException('connection refused'));

        $response = $this->createController($client)->testConnection($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate PreclinicalTrials.eu connection. Check URL and API availability.', $payload['message']);
        $this->assertSame('connection refused', $payload['detail']);
    }

    #[Test]
    public function testConnectionReturns502WhenAppCodeMismatch(): void
    {
        $app = $this->createMock(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::v4());
        $app->method('getCode')->willReturn(AppCode::Elabftw);

        $client = $this->createMock(PreclinicalTrialsClientInterface::class);
        $client->expects($this->never())->method('listProtocols');

        $response = $this->createController($client)->testConnection($app);

        $this->assertSame(502, $response->getStatusCode());
    }

    #[Test]
    public function listProtocolsReturnsServiceSummaries(): void
    {
        $connectedApp = $this->createConnectedApp();
        $service = $this->createMock(PreclinicalTrialsServiceInterface::class);
        $service->method('listProtocolSummaries')->with($connectedApp)->willReturn([
            ['id' => 'PCTE0000119', 'title' => 'Protocol A'],
        ]);

        $response = $this->createController($this->createMock(PreclinicalTrialsClientInterface::class), $service)->listProtocols($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('PCTE0000119', $payload['protocols'][0]['id']);
    }

    #[Test]
    public function importProtocolCanCreateNewInvestigation(): void
    {
        $connectedApp = $this->createConnectedApp();
        $service = $this->createMock(PreclinicalTrialsServiceInterface::class);
        $service->method('importSelectedProtocol')->with($connectedApp, 'PCTE0000119', null)->willReturn([
            'type' => 'investigation',
            'id' => 'project-id',
            'name' => 'PCTE0000119 - Protocol A',
        ]);

        $request = new Request(content: json_encode(['protocolId' => 'PCTE0000119', 'target' => 'new-investigation'], \JSON_THROW_ON_ERROR));
        $response = $this->createController($this->createMock(PreclinicalTrialsClientInterface::class), $service)->importProtocol($connectedApp, $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame('investigation', $payload['target']['type']);
    }

    #[Test]
    public function importProtocolCanLinkExistingStudy(): void
    {
        $connectedApp = $this->createConnectedApp();
        $studyId = Uuid::v4();
        $service = $this->createMock(PreclinicalTrialsServiceInterface::class);
        $service->method('linkSelectedProtocolToStudy')->with($connectedApp, 'PCTE0000119', $studyId)->willReturn([
            'type' => 'study',
            'id' => $studyId->toRfc4122(),
            'name' => 'Study A',
        ]);

        $request = new Request(content: json_encode([
            'protocolId' => 'PCTE0000119',
            'target' => 'existing-study',
            'studyId' => $studyId->toRfc4122(),
        ], \JSON_THROW_ON_ERROR));
        $response = $this->createController($this->createMock(PreclinicalTrialsClientInterface::class), $service)->importProtocol($connectedApp, $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('study', $payload['target']['type']);
    }
}
