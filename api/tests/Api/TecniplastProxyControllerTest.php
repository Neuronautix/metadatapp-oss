<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastHttpClientInterface;
use App\ConnectedApps\Apps\Tecniplast\Import\DvcImportResult;
use App\ConnectedApps\Apps\Tecniplast\Import\DvcImportServiceInterface;
use App\ConnectedApps\Apps\Tecniplast\Operations\TecniplastOperationsDataProvider;
use App\Controller\Api\TecniplastProxyController;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\AppCode;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TecniplastProxyControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private function createController(
        TecniplastHttpClientInterface $tecniplastClient,
        ?DvcImportServiceInterface $dvcImportService = null,
        ?EntityManagerInterface $entityManager = null,
    ): TecniplastProxyController {
        return new TecniplastProxyController(
            $tecniplastClient,
            new TecniplastOperationsDataProvider(),
            $dvcImportService ?? $this->createMock(DvcImportServiceInterface::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            false,
        );
    }

    private function createSecurityBypass(): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        return $security;
    }

    private function createTecniplastConnectedApp(): object
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        return ConnectedAppFactory::createOne([
            'code' => AppCode::Tecniplast,
            'token' => 'unit-test-token',
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://custom-dvc.example',
        ]);
    }

    #[Test]
    public function dvcImportEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('POST');
        $route = $router->match('/connected_apps/some-app-id/dvc/42/import');

        $this->assertSame('api_tecniplast_dvc_import', $route['_route']);
        $this->assertSame('some-app-id', $route['id']);
        $this->assertSame('42', $route['taskId']);
    }

    #[Test]
    public function dvcImportEndpointReturnsMissingExperimentIriError(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();
        $client = $this->createMock(TecniplastHttpClientInterface::class);
        $controller = $this->createController($client);

        $response = $controller->importTaskResult(42, $connectedApp, new Request(content: '{}'));

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function dvcImportEndpointReturnsNotFoundWhenExperimentMissing(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();
        $client = $this->createMock(TecniplastHttpClientInterface::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $em->method('getRepository')->willReturn($repo);
        $repo->method('find')->willReturn(null);

        $controller = $this->createController($client, null, $em);

        $request = new Request(content: json_encode(['experimentIri' => '/experiments/00000000-0000-0000-0000-000000000000']));
        $response = $controller->importTaskResult(42, $connectedApp, $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function dvcImportEndpointReturnsImportSummaryOnSuccess(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::Tecniplast,
            'token' => 'unit-test-token',
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://custom-dvc.example',
        ]);
        $experiment = ExperimentFactory::createOne([
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $importResult = new DvcImportResult();
        $importResult->processedRows = 5;
        $importResult->importedRows = 4;
        $importResult->skippedRows = 1;

        $mockStream = fopen('php://memory', 'r');
        $mockResponse = $this->createMock(ResponseInterface::class);

        $client = $this->createMock(TecniplastHttpClientInterface::class);
        $client->expects($this->once())
            ->method('downloadTaskResult')
            ->with('unit-test-token', 42, 'https://custom-dvc.example')
            ->willReturn($mockResponse)
        ;
        $client->method('toStream')->willReturn($mockStream);

        $importService = $this->createMock(DvcImportServiceInterface::class);
        $importService->expects($this->once())
            ->method('importFromZip')
            ->willReturn($importResult);

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $em->method('getRepository')->willReturn($repo);
        $repo->method('find')->willReturn($experiment);

        $controller = $this->createController($client, $importService, $em);

        $experimentId = (string) $experiment->getId();
        $experimentIri = '/experiments/' . $experimentId;
        $request = new Request(content: json_encode(['experimentIri' => $experimentIri]));
        $response = $controller->importTaskResult(42, $connectedApp, $request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame(42, $body['taskId']);
        $this->assertSame($experimentIri, $body['experimentIri']);
        $this->assertSame(5, $body['processedRows']);
        $this->assertSame(4, $body['importedRows']);
        $this->assertSame(1, $body['skippedRows']);
    }

    #[Test]
    public function dvcTestApiKeyEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('POST');
        $route = $router->match('/connected_apps/test-id/dvc/test-api-key');

        $this->assertSame('api_tecniplast_dvc_test_api_key', $route['_route']);
        $this->assertSame('test-id', $route['id']);
    }

    #[Test]
    public function dvcTestApiKeyControllerReturnsPayloadFromClient(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('testApiKey')
            ->with('unit-test-token', 'https://custom-dvc.example')
            ->willReturn('f1234')
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->testApiKey($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('f1234', json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcTestApiKeyControllerReturnsObjectPayloadFromClient(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('testApiKey')
            ->with('unit-test-token', 'https://custom-dvc.example')
            ->willReturn(['facilityId' => '809412dc-4a3b-47fb-0'])
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->testApiKey($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'facilityId' => '809412dc-4a3b-47fb-0',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcTestApiKeyPrefersAccessTokenOverStaleApiKeyParameter(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $connectedApp = ConnectedAppFactory::createOne([
            'code' => AppCode::Tecniplast,
            'token' => null,
            'authenticationParameters' => [
                'apiKey' => 'stale-placeholder',
                'accessToken' => 'fresh-dvc-token',
            ],
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://custom-dvc.example',
        ]);

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('testApiKey')
            ->with('fresh-dvc-token', 'https://custom-dvc.example')
            ->willReturn('f1234')
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->testApiKey($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('f1234', json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcTestApiKeyControllerReturnsHelpfulMessageWhenUpstreamFails(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('testApiKey')
            ->with('unit-test-token', 'https://custom-dvc.example')
            ->willThrowException(new \RuntimeException('upstream timeout'))
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->testApiKey($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Unable to validate Tecniplast API key. Check key and DVC endpoint configuration.',
            'detail' => 'upstream timeout',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcSubmitRejectsUnexpectedDatetimeFormat(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->never())
            ->method('submitTask')
        ;

        $controller = $this->createController($tecniplastClient);

        $request = new Request(content: json_encode([
            'aggrType' => 'HOUR',
            'ids' => ['S81P-40648'],
            'metrics' => ['ACTIVATION'],
            'outputFilename' => 'dvc-export',
            'resourceType' => 'CAGE',
            'start' => '2026-02-18T00:00:00.000Z',
            'stop' => '2026-02-19T00:00:00Z',
            'partitionType' => 'SINGLE_FILE_BY_CAGE',
        ]));

        $response = $controller->submitTask($request, $connectedApp);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Invalid DVC submission payload.',
            'detail' => 'Unexpected datetime format for "start". Expected YYYY-MM-DDTHH:MM:SSZ (e.g. 2023-07-21T17:32:28Z)',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcSubmitReturnsNumericTaskId(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('submitTask')
            ->with('unit-test-token', $this->callback(static function (array $payload): bool {
                return '2026-02-18T00:00:00Z' === $payload['start']
                    && '2026-02-19T00:00:00Z' === $payload['stop'];
            }), 'https://custom-dvc.example')
            ->willReturn([
                'taskId' => 26099,
                'requestId' => 'req-26099',
            ])
        ;

        $controller = $this->createController($tecniplastClient);

        $request = new Request(content: json_encode([
            'aggrType' => 'HOUR',
            'ids' => ['S81P-40648'],
            'metrics' => ['ACTIVATION'],
            'outputFilename' => 'dvc-export',
            'resourceType' => 'CAGE',
            'start' => '2026-02-18T00:00:00Z',
            'stop' => '2026-02-19T00:00:00Z',
            'partitionType' => 'SINGLE_FILE_BY_CAGE',
        ]));

        $response = $controller->submitTask($request, $connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'taskId' => 26099,
            'requestId' => 'req-26099',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcTaskStateReturnsObjectShapeExpectedByFrontend(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('getTaskState')
            ->with('unit-test-token', 26099, 'https://custom-dvc.example')
            ->willReturn([
                'id' => 26099,
                'state' => 'COMPLETED',
                'createdAt' => '2026-03-13T09:30:00Z',
                'finishedAt' => '2026-03-13T09:31:00Z',
                'fileSize' => 1024,
                'checksum' => 'abc123',
            ])
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->getTaskState(26099, $connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'id' => 26099,
            'state' => 'COMPLETED',
            'createdAt' => '2026-03-13T09:30:00Z',
            'finishedAt' => '2026-03-13T09:31:00Z',
            'fileSize' => 1024,
            'checksum' => 'abc123',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function dvcDownloadReturnsAuthenticatedZipContent(): void
    {
        $connectedApp = $this->createTecniplastConnectedApp();
        $zipContent = "PK\x03\x04test-zip-content";

        $responseInterface = $this->createMock(ResponseInterface::class);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $zipContent);
        rewind($stream);

        $tecniplastClient = $this->createMock(TecniplastHttpClientInterface::class);
        $tecniplastClient
            ->expects($this->once())
            ->method('downloadTaskResult')
            ->with('unit-test-token', 26099, 'https://custom-dvc.example')
            ->willReturn($responseInterface)
        ;
        $tecniplastClient
            ->expects($this->once())
            ->method('toStream')
            ->with($responseInterface)
            ->willReturn($stream)
        ;

        $controller = $this->createController($tecniplastClient);

        $response = $controller->downloadTaskResult(26099, $connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="task_26099.zip"', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertSame($zipContent, $content);
    }

    #[Test]
    public function operationsFacilitiesEndpointIsReachable(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/tp/facilities');

        $this->assertSame('api_tecniplast_operations_facilities', $route['_route']);
    }

    #[Test]
    public function operationsFacilitiesEndpointReturnsDataForAuthenticatedUser(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/tp/facilities');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertNotEmpty($payload);
        $this->assertSame('FAC-NE-01', $payload[0]['id']);
        $this->assertArrayHasKey('stats', $payload[0]);
    }

    #[Test]
    public function operationsRoomsEndpointFiltersByFacilityId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/tp/rooms?facilityId=FAC-NE-01');

        $this->assertResponseIsSuccessful();

        $payload = $response->toArray();
        $this->assertCount(3, $payload);
        $this->assertSame('FAC-NE-01', $payload[0]['facilityId']);
    }

    #[Test]
    public function operationsAlarmsEndpointReturnsPaginatedShapeExpectedByFrontend(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/tp/alarms?facilityId=FAC-NE-01&status=active&page=1&pageSize=10');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertSame(1, $payload['page']);
        $this->assertSame(10, $payload['pageSize']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertGreaterThanOrEqual(1, $payload['total']);
    }

    #[Test]
    public function operationsSensorsEndpointFiltersByMetricAndStatus(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/tp/sensors?facilityId=FAC-NE-01&metric=temperature&status=offline');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertCount(1, $payload);
        $this->assertSame('SENSOR-004', $payload[0]['id']);
        $this->assertSame('temperature', $payload[0]['metric']);
        $this->assertSame('offline', $payload[0]['status']);
    }

    #[Test]
    public function operationsConditionsEndpointsReturnFrontendContractShapes(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        $snapshotResponse = $client->request('GET', '/tp/conditions/snapshot', [
            'query' => [
                'scopeType' => 'room',
                'scopeId' => 'RM-201',
                'at' => '2026-03-19T10:00:00Z',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $snapshot = $snapshotResponse->toArray();
        $this->assertSame('room', $snapshot['scopeType']);
        $this->assertSame('RM-201', $snapshot['scopeId']);
        $this->assertArrayHasKey('metrics', $snapshot);
        $this->assertArrayHasKey('thresholds', $snapshot);
        $this->assertArrayHasKey('alarms', $snapshot);

        $readingsClient = static::createClient();
        $readingsClient->loginUser($user);
        $readingsResponse = $readingsClient->request('GET', '/tp/conditions/readings', [
            'query' => [
                'scopeType' => 'room',
                'scopeId' => 'RM-201',
                'metric' => 'temperature',
                'from' => '2026-03-19T09:00:00Z',
                'to' => '2026-03-19T10:00:00Z',
                'interval' => '30m',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $readings = $readingsResponse->toArray();
        $this->assertSame('temperature', $readings['metric']);
        $this->assertSame('C', $readings['unit']);
        $this->assertCount(3, $readings['points']);

        $thresholdsClient = static::createClient();
        $thresholdsClient->loginUser($user);
        $thresholdsResponse = $thresholdsClient->request('GET', '/tp/conditions/thresholds', [
            'query' => [
                'scopeType' => 'room',
                'scopeId' => 'RM-201',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $thresholds = $thresholdsResponse->toArray();
        $this->assertCount(6, $thresholds);
        $this->assertSame('temperature', $thresholds[0]['metric']);

        $updateClient = static::createClient();
        $updateClient->loginUser($user);
        $updateResponse = $updateClient->request('PUT', '/tp/conditions/thresholds', [
            'query' => [
                'scopeType' => 'room',
                'scopeId' => 'RM-201',
            ],
            'json' => [
                'thresholds' => [
                    ['metric' => 'temperature', 'min' => 19, 'max' => 27],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $updated = $updateResponse->toArray();
        $this->assertSame(19, $updated[0]['min']);
        $this->assertSame('C', $updated[0]['unit']);
    }
}
