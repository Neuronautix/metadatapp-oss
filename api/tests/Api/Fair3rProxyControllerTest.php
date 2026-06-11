<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\Fair3rResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use App\Controller\Api\Fair3rProxyController;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AppCode;
use App\Service\Fair3rDemoCsvCatalog;
use App\Service\Fair3rFdfMapper;
use App\Service\FairAssessmentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class Fair3rProxyControllerTest extends ApiTestCase
{
    private function createController(Fair3rHttpClientInterface $httpClient): Fair3rProxyController
    {
        return new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(EntityManagerInterface::class),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );
    }

    private function createSecurityBypass(): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        return $security;
    }

    private function createFair3rConnectedApp(string $externalUrl = 'https://fair3r.example.com', ?string $token = 'mock-fair3r-token'): ConnectedApp
    {
        $app = $this->createMock(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::v4());
        $app->method('getCode')->willReturn(AppCode::Fair3r);
        $app->method('getExternalUrl')->willReturn($externalUrl);
        $app->method('getToken')->willReturn($token);
        $app->method('getAuthenticationParameters')->willReturn(['owner_org' => 'metadatapp-demo']);

        return $app;
    }

    private function createFair3rConnectedAppWithAuthenticationParameters(array $authenticationParameters): ConnectedApp
    {
        $app = $this->createMock(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::v4());
        $app->method('getCode')->willReturn(AppCode::Fair3r);
        $app->method('getExternalUrl')->willReturn('https://validation.fair3r.fr');
        $app->method('getToken')->willReturn('mock-fair3r-token');
        $app->method('getAuthenticationParameters')->willReturn($authenticationParameters);

        return $app;
    }

    #[Test]
    public function fair3rTestConnectionEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('POST');
        $route = $router->match('/connected_apps/test-id/fair3r/test-connection');

        $this->assertSame('api_fair3r_test_connection', $route['_route']);
        $this->assertSame('test-id', $route['id']);
    }

    #[Test]
    public function fair3rShowDatasetEndpointIsReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getContext()->setMethod('GET');
        $route = $router->match('/connected_apps/test-id/fair3r/datasets/my-top-project-to-share');

        $this->assertSame('api_fair3r_show_dataset', $route['_route']);
        $this->assertSame('test-id', $route['id']);
        $this->assertSame('my-top-project-to-share', $route['datasetName']);
    }

    /**
     * @test
     */
    #[Test]
    public function connectionControllerReturnsDatasetCount(): void
    {
        $connectedApp = $this->createFair3rConnectedApp();

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);

        $responseDto = new Fair3rResponseDto(
            help: null,
            success: true,
            result: ['dataset-1', 'dataset-2', 'dataset-3'],
        );

        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with('POST', '/api/3/action/package_list', $this->callback(static function (array $opts): bool {
                return isset($opts['base_uri']) && str_starts_with($opts['base_uri'], 'https://fair3r.example.com')
                    && isset($opts['headers']['Authorization']);
            }))
            ->willReturn($responseDto)
        ;

        $controller = $this->createController($httpClient);
        $response = $controller->testConnection($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame(3, $payload['datasetsCount']);
        $this->assertSame('https://fair3r.example.com', $payload['externalUrl']);
    }

    #[Test]
    public function connectionControllerPrefersFair3rAccessTokenOverStaleGenericToken(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getId')->willReturn(Uuid::v4());
        $connectedApp->method('getCode')->willReturn(AppCode::Fair3r);
        $connectedApp->method('getExternalUrl')->willReturn('https://fair3r.example.com');
        $connectedApp->method('getToken')->willReturn('stale-generic-token');
        $connectedApp->method('getAuthenticationParameters')->willReturn(['accessToken' => 'fresh-fair3r-token']);

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with('POST', '/api/3/action/package_list', $this->callback(static function (array $opts): bool {
                return 'fresh-fair3r-token' === ($opts['headers']['Authorization'] ?? null);
            }))
            ->willReturn(new Fair3rResponseDto(success: true, result: []))
        ;

        $controller = $this->createController($httpClient);
        $response = $controller->testConnection($connectedApp);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    #[Test]
    public function connectionControllerReturns502WhenUpstreamFails(): void
    {
        $connectedApp = $this->createFair3rConnectedApp();

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new \RuntimeException('connection refused'))
        ;

        $controller = $this->createController($httpClient);
        $response = $controller->testConnection($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate Fair3R connection. Check URL and API token.', $payload['message']);
        $this->assertSame('connection refused', $payload['detail']);
    }

    /**
     * @test
     */
    #[Test]
    public function connectionControllerReturns502WhenExternalUrlMissing(): void
    {
        // assertValidConnectedApp throws AccessDeniedException (which is caught by the try/catch
        // in testConnection and returned as a 502 error response with the exception message)
        $connectedApp = $this->createFair3rConnectedApp(externalUrl: '');

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $controller = $this->createController($httpClient);
        $response = $controller->testConnection($connectedApp);

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate Fair3R connection. Check URL and API token.', $payload['message']);
    }

    #[Test]
    public function showDatasetReturnsParsedFdfMetadataFromResourceExtras(): void
    {
        $connectedApp = $this->createFair3rConnectedApp();
        $fdf = [
            'identifier' => '10.0000/demo',
            'titles' => [['title' => 'My top project to share']],
            'publicationYear' => 2026,
        ];

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with('GET', '/api/3/action/package_show', $this->callback(static function (array $options): bool {
                return ['id' => 'my-top-project-to-share'] === ($options['query'] ?? null);
            }))
            ->willReturn(new Fair3rResponseDto(success: true, result: [
                'id' => 'package-id',
                'name' => 'my-top-project-to-share',
                'title' => 'My top project to share',
                'resources' => [[
                    'id' => 'resource-id',
                    'name' => 'Metadatapp FAIR3R JSON',
                    'format' => 'JSON',
                    'metadatapp_fdf_json' => json_encode($fdf, \JSON_THROW_ON_ERROR),
                ]],
            ]))
        ;

        $controller = $this->createController($httpClient);
        $response = $controller->showDataset($connectedApp, 'my-top-project-to-share');

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('my-top-project-to-share', $payload['dataset']['name']);
        $this->assertSame('10.0000/demo', $payload['fdf']['identifier']);
        $this->assertSame('My top project to share', $payload['fdf']['titles'][0]['title']);
        $this->assertSame('package-id', $payload['ckan']['id']);
    }

    #[Test]
    public function showDatasetReturns403WhenFair3rTokenCannotReadPrivateDataset(): void
    {
        $connectedApp = $this->createFair3rConnectedApp();

        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Fair3rResponseDto(
                success: false,
                error: [
                    '__type' => 'Authorization Error',
                    'message' => 'Access denied: User not authorized to read package ca7f31b7-a604-49b0-a82e-822e1e8d1423',
                ],
            ))
        ;

        $controller = $this->createController($httpClient);
        $response = $controller->showDataset($connectedApp, 'my-top-project-to-share');

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Access denied: User not authorized to read package ca7f31b7-a604-49b0-a82e-822e1e8d1423', $payload['message']);
        $this->assertSame('Unable to read Fair3R dataset.', $payload['detail']);
    }

    #[Test]
    public function pushInvestigationCreatesPackageAndFdfResourceForExplicitTargetDataset(): void
    {
        $projectId = Uuid::v4();
        $project = (new Project())
            ->setId($projectId)
            ->setName('Demo investigation')
            ->setGoal('Mock dataset staged for FAIR3R.')
            ->setDescription('Mock dataset staged for FAIR3R.')
            ->setStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setUser($this->createProjectUser())
        ;

        $connectedApp = $this->createFair3rConnectedApp();
        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function (string $method, string $uri, array $options) use ($projectId): Fair3rResponseDto {
                $this->assertArrayHasKey('base_uri', $options);
                $this->assertSame('https://fair3r.example.com/', $options['base_uri']);

                if ('/api/3/action/package_show' === $uri) {
                    throw new \RuntimeException('Not found');
                }

                if ('/api/3/action/package_create' === $uri) {
                    $this->assertSame('POST', $method);
                    $this->assertSame('damien-test-manuel', $options['json']['name']);
                    $this->assertSame('metadatapp-demo', $options['json']['owner_org']);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => $options['json']['name'],
                        'resources' => [],
                    ]);
                }

                $this->assertSame('/api/3/action/resource_create', $uri);
                $this->assertSame('POST', $method);
                $this->assertSame('damien-test-manuel', $options['json']['package_id']);
                $this->assertSame('Metadatapp FAIR3R JSON', $options['json']['name']);
                $this->assertSame('JSON', $options['json']['format']);
                $this->assertNotEmpty($options['json']['metadatapp_fdf_json']);

                return new Fair3rResponseDto(success: true, result: ['id' => 'resource-id']);
            })
        ;

        $controller = new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createEntityManagerReturningProject($project),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );

        $response = $controller->pushInvestigation($connectedApp, new Request(content: json_encode([
            'investigationId' => $projectId->toRfc4122(),
            'targetDatasetName' => 'damien-test-manuel',
        ], \JSON_THROW_ON_ERROR)));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['ok']);
        $this->assertSame('damien-test-manuel', $payload['ckanName']);
        $this->assertSame('resource-id', $payload['resourceId']);
    }

    #[Test]
    public function pushInvestigationAddsSelectedDemoCsvResources(): void
    {
        $projectId = Uuid::v4();
        $project = (new Project())
            ->setId($projectId)
            ->setName('Demo investigation')
            ->setGoal('Mock dataset staged for FAIR3R.')
            ->setDescription('Mock dataset staged for FAIR3R.')
            ->setStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setUser($this->createProjectUser())
        ;

        $createdCsvResources = [];
        $connectedApp = $this->createFair3rConnectedApp();
        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->exactly(5))
            ->method('sendRequest')
            ->willReturnCallback(function (string $method, string $uri, array $options) use (&$createdCsvResources): Fair3rResponseDto {
                if ('/api/3/action/package_show' === $uri) {
                    throw new \RuntimeException('Not found');
                }

                if ('/api/3/action/package_create' === $uri) {
                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => $options['json']['name'],
                        'resources' => [],
                    ]);
                }

                $this->assertSame('/api/3/action/resource_create', $uri);
                $this->assertSame('POST', $method);
                $this->assertSame('damien-test-manuel', $options['json']['package_id']);

                if ('CSV' !== $options['json']['format']) {
                    $this->assertSame('Metadatapp FAIR3R JSON', $options['json']['name']);

                    return new Fair3rResponseDto(success: true, result: ['id' => 'fdf-resource-id']);
                }

                $key = $options['json']['metadatapp_demo_csv_key'];
                $createdCsvResources[$key] = $options['json'];
                $this->assertStringStartsWith('Demo CSV - ', $options['json']['name']);
                $this->assertSame('text/csv', $options['json']['mimetype']);
                $this->assertStringContainsString('/fair3r/demo-results/' . $key . '.csv', $options['json']['url']);
                $this->assertNotEmpty($options['json']['metadatapp_demo_csv']);

                return new Fair3rResponseDto(success: true, result: ['id' => $key . '-resource-id']);
            })
        ;

        $controller = new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createEntityManagerReturningProject($project),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );

        $response = $controller->pushInvestigation($connectedApp, new Request(
            content: json_encode([
                'investigationId' => $projectId->toRfc4122(),
                'targetDatasetName' => 'damien-test-manuel',
                'demoCsvKeys' => ['bodyweight-longitudinal', 'open-field-behavior', 'bodyweight-longitudinal', 'unknown'],
            ], \JSON_THROW_ON_ERROR),
            server: ['HTTP_HOST' => 'osoma.metadatapp.test', 'HTTPS' => 'on'],
        ));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'bodyweight-longitudinal' => 'bodyweight-longitudinal-resource-id',
            'open-field-behavior' => 'open-field-behavior-resource-id',
        ], $payload['demoCsvResourceIds']);
        $this->assertSame(['bodyweight-longitudinal', 'open-field-behavior'], array_keys($createdCsvResources));
    }

    #[Test]
    public function pushInvestigationPatchesExistingFdfResource(): void
    {
        $projectId = Uuid::v4();
        $project = (new Project())
            ->setId($projectId)
            ->setName('Demo investigation')
            ->setGoal('Mock dataset staged for FAIR3R.')
            ->setStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setUser($this->createProjectUser())
        ;

        $connectedApp = $this->createFair3rConnectedApp();
        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function (string $method, string $uri, array $options): Fair3rResponseDto {
                if ('/api/3/action/package_show' === $uri) {
                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'resources' => [
                            ['id' => 'existing-resource-id', 'name' => 'Metadatapp FAIR3R JSON'],
                        ],
                    ]);
                }

                if ('/api/3/action/package_patch' === $uri) {
                    $this->assertSame('POST', $method);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'resources' => [
                            ['id' => 'existing-resource-id', 'name' => 'Metadatapp FAIR3R JSON'],
                        ],
                    ]);
                }

                $this->assertSame('/api/3/action/resource_patch', $uri);
                $this->assertSame('existing-resource-id', $options['json']['id']);

                return new Fair3rResponseDto(success: true, result: ['id' => 'existing-resource-id']);
            })
        ;

        $controller = new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createEntityManagerReturningProject($project),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );

        $response = $controller->pushInvestigation($connectedApp, new Request(content: json_encode([
            'investigationId' => $projectId->toRfc4122(),
        ], \JSON_THROW_ON_ERROR)));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('existing-resource-id', $payload['resourceId']);
    }

    #[Test]
    public function pushInvestigationCanTargetConfiguredExistingDataset(): void
    {
        $projectId = Uuid::v4();
        $project = (new Project())
            ->setId($projectId)
            ->setName('Demo investigation')
            ->setGoal('Mock dataset staged for FAIR3R.')
            ->setStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setUser($this->createProjectUser())
        ;

        $connectedApp = $this->createFair3rConnectedAppWithAuthenticationParameters([
            'targetDatasetName' => 'damien-test-manuel',
            'ownerOrg' => 'metadatapp-demo',
        ]);
        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function (string $method, string $uri, array $options): Fair3rResponseDto {
                if ('/api/3/action/package_show' === $uri) {
                    $this->assertSame(['id' => 'damien-test-manuel'], $options['query']);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => 'damien-test-manuel',
                        'resources' => [],
                    ]);
                }

                if ('/api/3/action/package_patch' === $uri) {
                    $this->assertSame('damien-test-manuel', $options['json']['name']);
                    $this->assertSame('damien-test-manuel', $options['json']['id']);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => 'damien-test-manuel',
                        'resources' => [],
                    ]);
                }

                $this->assertSame('/api/3/action/resource_create', $uri);
                $this->assertSame('damien-test-manuel', $options['json']['package_id']);

                return new Fair3rResponseDto(success: true, result: ['id' => 'resource-id']);
            })
        ;

        $controller = new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createEntityManagerReturningProject($project),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );

        $response = $controller->pushInvestigation($connectedApp, new Request(content: json_encode([
            'investigationId' => $projectId->toRfc4122(),
        ], \JSON_THROW_ON_ERROR)));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('damien-test-manuel', $payload['ckanName']);
        $this->assertSame('https://validation.fair3r.fr/dataset/damien-test-manuel', $payload['packageUrl']);
    }

    #[Test]
    public function pushInvestigationNormalizesConfiguredDatasetNameToCkanSlug(): void
    {
        $projectId = Uuid::v4();
        $project = (new Project())
            ->setId($projectId)
            ->setName('Demo investigation')
            ->setGoal('Mock dataset staged for FAIR3R.')
            ->setStartAt(new \DateTimeImmutable('2026-01-01'))
            ->setUser($this->createProjectUser())
        ;

        $connectedApp = $this->createFair3rConnectedAppWithAuthenticationParameters([
            'targetDatasetName' => 'Damien-test-manuel',
            'ownerOrg' => 'metadatapp-demo',
        ]);
        $httpClient = $this->createMock(Fair3rHttpClientInterface::class);
        $httpClient->expects($this->exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function (string $method, string $uri, array $options): Fair3rResponseDto {
                if ('/api/3/action/package_show' === $uri) {
                    $this->assertSame(['id' => 'damien-test-manuel'], $options['query']);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => 'damien-test-manuel',
                        'resources' => [],
                    ]);
                }

                if ('/api/3/action/package_patch' === $uri) {
                    $this->assertSame('damien-test-manuel', $options['json']['name']);
                    $this->assertSame('damien-test-manuel', $options['json']['id']);

                    return new Fair3rResponseDto(success: true, result: [
                        'id' => 'package-id',
                        'name' => 'damien-test-manuel',
                        'resources' => [],
                    ]);
                }

                $this->assertSame('/api/3/action/resource_create', $uri);
                $this->assertSame('damien-test-manuel', $options['json']['package_id']);

                return new Fair3rResponseDto(success: true, result: ['id' => 'resource-id']);
            })
        ;

        $controller = new Fair3rProxyController(
            $httpClient,
            $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            $this->createEntityManagerReturningProject($project),
            new Fair3rFdfMapper(new FairAssessmentService()),
            new Fair3rDemoCsvCatalog(),
        );

        $response = $controller->pushInvestigation($connectedApp, new Request(content: json_encode([
            'investigationId' => $projectId->toRfc4122(),
        ], \JSON_THROW_ON_ERROR)));

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('damien-test-manuel', $payload['ckanName']);
    }

    private function createProjectUser(): User
    {
        $account = new Account();
        $account->setId(Uuid::v4());

        return (new User())
            ->setEmail('researcher@example.com')
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setAccount($account)
        ;
    }

    private function createEntityManagerReturningProject(Project $project): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Project::class)->willReturn($repository);

        return $entityManager;
    }
}
