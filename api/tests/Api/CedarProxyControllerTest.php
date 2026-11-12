<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\Cedar\Client\CedarClientInterface;
use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\Controller\Api\CedarProxyController;
use App\ConnectedApps\Apps\Cedar\Export\CanonicalFormCedarTemplateBuilder;
use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Crosswalk\CrosswalkBootstrapper;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CedarProxyControllerTest extends ApiTestCase
{
    private function createController(CedarClientInterface $client, ?EntityManagerInterface $em = null, ?Security $security = null): CedarProxyController
    {
        $em = $em ?? $this->createMock(EntityManagerInterface::class);

        return new CedarProxyController(
            $client,
            $em,
            $security ?? $this->createSecurityBypass(),
            $this->createMock(LoggerInterface::class),
            new CrosswalkBootstrapper($em),
            new CanonicalFormCedarTemplateBuilder(),
            new ConnectedAppRateLimiter(new ArrayAdapter(), new MockClock()),
        );
    }

    private function createSecurityBypass(): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);

        return $security;
    }

    private function createCedarConnectedApp(?string $token = 'mock-cedar-key', string $externalUrl = 'https://resource.metadatacenter.org'): ConnectedApp
    {
        $app = $this->createMock(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::v4());
        $app->method('getCode')->willReturn(AppCode::Cedar);
        $app->method('getExternalUrl')->willReturn($externalUrl);
        $app->method('getToken')->willReturn($token);

        return $app;
    }

    #[Test]
    public function cedarArtifactEndpointsAreReachableAtConnectedAppsPath(): void
    {
        $router = static::getContainer()->get(RouterInterface::class);

        $router->getContext()->setMethod('GET');
        $get = $router->match('/connected_apps/test-id/cedar/artifacts/template');
        $this->assertSame('api_cedar_get_artifact', $get['_route']);
        $this->assertSame('template', $get['type']);

        $router->getContext()->setMethod('POST');
        $create = $router->match('/connected_apps/test-id/cedar/artifacts/instance');
        $this->assertSame('api_cedar_create_artifact', $create['_route']);
        $this->assertSame('instance', $create['type']);

        $update = $router->match('/connected_apps/test-id/cedar/artifacts/element/update');
        $this->assertSame('api_cedar_update_artifact', $update['_route']);

        $delete = $router->match('/connected_apps/test-id/cedar/artifacts/field/delete');
        $this->assertSame('api_cedar_delete_artifact', $delete['_route']);

        $validate = $router->match('/connected_apps/test-id/cedar/validate');
        $this->assertSame('api_cedar_validate_artifact', $validate['_route']);

        $import = $router->match('/connected_apps/test-id/cedar/import-template');
        $this->assertSame('api_cedar_import_template', $import['_route']);
    }

    #[Test]
    public function testConnectionReturnsUserCount(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->method('getBaseUrl')->willReturn('https://resource.metadatacenter.org');
        $client->expects($this->once())->method('getUsers')->willReturn(['users' => [['@id' => 'u1'], ['@id' => 'u2']]]);

        $response = $this->createController($client)->testConnection($this->createCedarConnectedApp());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame(2, $payload['usersCount']);
        $this->assertSame('https://resource.metadatacenter.org', $payload['externalUrl']);
    }

    #[Test]
    public function getArtifactPassesTypeAndIriToClient(): void
    {
        $iri = 'https://repo.metadatacenter.org/templates/abc';
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('getArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), CedarArtifactType::Template, $iri)
            ->willReturn(['@id' => $iri, 'schema:name' => 'Demo'])
        ;

        $request = new Request(query: ['iri' => $iri]);
        $response = $this->createController($client)->getArtifact($this->createCedarConnectedApp(), $request, 'template');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame($iri, $payload['artifact']['@id']);
    }

    #[Test]
    public function getArtifactReturns400WhenIriMissing(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->never())->method('getArtifact');

        $response = $this->createController($client)->getArtifact($this->createCedarConnectedApp(), new Request(), 'template');

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function getArtifactReturns400ForUnknownType(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->never())->method('getArtifact');

        $response = $this->createController($client)->getArtifact($this->createCedarConnectedApp(), new Request(query: ['iri' => 'x']), 'nonsense');

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function createArtifactReturns201WithServerAssignedArtifact(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('createArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), CedarArtifactType::Template, ['schema:name' => 'New'])
            ->willReturn(['@id' => 'https://repo.metadatacenter.org/templates/new', 'schema:name' => 'New'])
        ;

        $request = new Request(content: json_encode(['schema:name' => 'New'], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->createArtifact($this->createCedarConnectedApp(), $request, 'template');

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('https://repo.metadatacenter.org/templates/new', $payload['artifact']['@id']);
    }

    #[Test]
    public function createArtifactAcceptsArtifactEnvelope(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('createArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), CedarArtifactType::Field, ['schema:name' => 'F'])
            ->willReturn(['@id' => 'field-id'])
        ;

        $request = new Request(content: json_encode(['artifact' => ['schema:name' => 'F']], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->createArtifact($this->createCedarConnectedApp(), $request, 'field');

        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function updateArtifactRequiresIriAndArtifact(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->never())->method('updateArtifact');

        $request = new Request(content: json_encode(['iri' => '', 'artifact' => ['a' => 1]], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->updateArtifact($this->createCedarConnectedApp(), $request, 'template');

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function deleteArtifactReturnsOkOnSuccess(): void
    {
        $iri = 'https://repo.metadatacenter.org/templates/gone';
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('deleteArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), CedarArtifactType::Template, $iri)
        ;

        $request = new Request(content: json_encode(['iri' => $iri], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->deleteArtifact($this->createCedarConnectedApp(), $request, 'template');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame($iri, $payload['deleted']);
    }

    #[Test]
    public function validateArtifactReturnsServerReport(): void
    {
        $artifact = ['@type' => 'https://schema.metadatacenter.org/core/Template', 'schema:name' => 'T'];
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('validateArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), $artifact, null)
            ->willReturn(['validates' => true, 'warnings' => [], 'errors' => []])
        ;

        $request = new Request(content: json_encode(['artifact' => $artifact], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->validateArtifact($this->createCedarConnectedApp(), $request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['validates']);
    }

    #[Test]
    public function validateArtifactForwardsExplicitType(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('validateArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), ['schema:name' => 'x'], CedarArtifactType::Instance)
            ->willReturn(['validates' => false, 'warnings' => [], 'errors' => ['nope']])
        ;

        $request = new Request(content: json_encode(['artifact' => ['schema:name' => 'x'], 'type' => 'instance'], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client)->validateArtifact($this->createCedarConnectedApp(), $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function importTemplatePersistsExternalTemplate(): void
    {
        $iri = 'https://repo.metadatacenter.org/templates/import-me';
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->once())
            ->method('getArtifact')
            ->with($this->isInstanceOf(ConnectedApp::class), CedarArtifactType::Template, $iri)
            ->willReturn([
                '@id' => $iri,
                'schema:name' => 'Imported CEDAR template',
                'schema:description' => 'A template fetched from CEDAR.',
                'pav:version' => '2.1.0',
                '_ui' => ['order' => ['weight', 'sex']],
                'properties' => [
                    'weight' => [
                        '@type' => 'https://schema.metadatacenter.org/core/TemplateField',
                        'schema:name' => 'Body weight',
                        '_ui' => ['inputType' => 'numeric'],
                    ],
                    'sex' => ['schema:name' => 'Sex'],
                ],
            ])
        ;

        $user = $this->createImportUser();

        $em = $this->createMock(EntityManagerInterface::class);
        // The controller persists the external template; the bootstrapper persists
        // the draft canonical form and crosswalk.
        $em->expects($this->exactly(3))->method('persist');
        $em->expects($this->once())->method('flush');
        $em->method('find')->with(User::class, $user->getId())->willReturn($user);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_SUPER_ADMIN')->willReturn(true);
        $security->method('getUser')->willReturn($user);

        $app = $this->createCedarConnectedApp();
        $app->method('getAccount')->willReturn($this->createAccount());

        $request = new Request(content: json_encode(['iri' => $iri], \JSON_THROW_ON_ERROR));
        $response = $this->createController($client, $em, $security)->importTemplate($app, $request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertTrue($payload['ok']);
        $this->assertSame('Imported CEDAR template', $payload['title']);
        $this->assertSame($iri, $payload['externalUrl']);
        $this->assertSame(2, $payload['fieldCount']);
        $this->assertArrayHasKey('crosswalkId', $payload);
    }

    #[Test]
    public function upstreamFailureSurfacesAs502(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->method('getUsers')->willThrowException(new \RuntimeException('connection refused'));

        $response = $this->createController($client)->testConnection($this->createCedarConnectedApp());

        $this->assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unable to validate CEDAR connection. Check URL and API key.', $payload['message']);
        $this->assertSame('connection refused', $payload['detail']);
    }

    #[Test]
    public function missingApiKeyIsRejected(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->expects($this->never())->method('getUsers');

        $response = $this->createController($client)->testConnection($this->createCedarConnectedApp(token: null));

        $this->assertSame(502, $response->getStatusCode());
    }

    private function createAccount(): Account
    {
        $account = new Account();
        $account->setId(Uuid::v4());

        return $account;
    }

    private function createImportUser(): User
    {
        return (new User())
            ->setId(Uuid::v4())
            ->setEmail('curator@example.com')
            ->setFirstName('Cura')
            ->setLastName('Tor')
            ->setAccount($this->createAccount())
        ;
    }
}
