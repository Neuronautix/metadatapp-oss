<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ConnectedAppTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ConnectedAppFactory::createMany(10, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/connected_apps');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/ConnectedApp',
            '@id' => '/connected_apps',
            '@type' => 'hydra:Collection',
        ]);

        $data = $response->toArray();
        $this->assertCount(10, $data['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(ConnectedApp::class);
    }

    #[Test]
    public function createProtocolIoConnectedApp(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'protocols.io Integration',
                'code' => '/app_codes/' . AppCode::ProtocolIo->value,
                'description' => 'protocols.io protocol repository integration',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/ConnectedApp',
            '@type' => 'ConnectedApp',
            'name' => 'protocols.io Integration',
            'code' => ['value' => AppCode::ProtocolIo->value],
        ]);
        $this->assertMatchesRegularExpression('~^/connected_apps/[0-9a-fA-F-]{36}$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(ConnectedApp::class);
    }

    #[Test]
    public function createFair3rConnectedApp(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Fair3R Integration',
                'code' => '/app_codes/' . AppCode::Fair3r->value,
                'description' => 'Fair3R data repository integration',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/ConnectedApp',
            '@type' => 'ConnectedApp',
            'name' => 'Fair3R Integration',
            'code' => ['value' => AppCode::Fair3r->value],
        ]);
        $this->assertMatchesResourceItemJsonSchema(ConnectedApp::class);
    }

    #[Test]
    public function createElabftwConnectedApp(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'eLabFTW Integration',
                'code' => '/app_codes/' . AppCode::Elabftw->value,
                'description' => 'eLabFTW electronic lab notebook integration',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/ConnectedApp',
            '@type' => 'ConnectedApp',
            'name' => 'eLabFTW Integration',
            'code' => ['value' => AppCode::Elabftw->value],
            'logoUrl' => '/images/apps/elabftw.svg',
        ]);
        $this->assertMatchesResourceItemJsonSchema(ConnectedApp::class);
    }

    #[Test]
    public function createOsfConnectedApp(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'OSF Integration',
                'code' => '/app_codes/' . AppCode::Osf->value,
                'description' => 'Open Science Framework repository integration',
                'externalUrl' => 'https://osf.io',
                'authenticationParameters' => [
                    'accessToken' => 'osf-access-token',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/ConnectedApp',
            '@type' => 'ConnectedApp',
            'name' => 'OSF Integration',
            'code' => ['value' => AppCode::Osf->value],
            'logoUrl' => '/images/apps/osf.svg',
            'authenticationParameterHints' => [
                'accessToken' => '******ken',
            ],
        ]);
    }

    #[Test]
    public function createConnectedAppWithAuthenticationParameters(): void
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Tecniplast Integration',
                'code' => '/app_codes/' . AppCode::Tecniplast->value,
                'authenticationParameters' => [
                    'accessToken' => 'tecniplast-access-token',
                    'refreshToken' => 'refresh-secret',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'Tecniplast Integration',
            'tokenHint' => '******ken',
            'authenticationParameterHints' => [
                'accessToken' => '******ken',
                'refreshToken' => '******ret',
            ],
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('authenticationParameters', $data);

        /** @var ConnectedApp|null $connectedApp */
        $connectedApp = static::getContainer()->get('doctrine')->getRepository(ConnectedApp::class)->findOneBy(['name' => 'Tecniplast Integration']);
        $this->assertNotNull($connectedApp);
        $this->assertSame([
            'accessToken' => 'tecniplast-access-token',
            'refreshToken' => 'refresh-secret',
        ], $connectedApp->getAuthenticationParameters());
    }

    #[Test]
    public function createInvalidConnectedApp(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/connected_apps', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'name',
                'code' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
        ]);
    }

    #[Test]
    public function updateConnectedApp(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);
        ConnectedAppFactory::createOne([
            'name' => 'Original Name',
            'code' => AppCode::Elabftw,
            'isActive' => false,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(ConnectedApp::class, ['name' => 'Original Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Integration Name',
                'authenticationParameters' => [
                    'apiKey' => 'mapp-api-key',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Integration Name',
            'authenticationParameterHints' => [
                'apiKey' => '******key',
            ],
        ]);
    }

    #[Test]
    public function patchConnectedAppCredentialsMergesWithExistingParameters(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);
        ConnectedAppFactory::createOne([
            'name' => 'eLabFTW Credential Merge',
            'code' => AppCode::Elabftw,
            'account' => $user->getAccount(),
            'user' => $user,
            'authenticationParameters' => [
                'apiKey' => 'old-api-key',
                'token' => 'old-token',
            ],
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(ConnectedApp::class, ['name' => 'eLabFTW Credential Merge']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'authenticationParameters' => [
                    'token' => 'new-token',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        /** @var ConnectedApp|null $connectedApp */
        $connectedApp = static::getContainer()->get('doctrine')->getRepository(ConnectedApp::class)->findOneBy(['name' => 'eLabFTW Credential Merge']);
        $this->assertNotNull($connectedApp);
        $this->assertSame([
            'apiKey' => 'old-api-key',
            'token' => 'new-token',
        ], $connectedApp->getAuthenticationParameters());
    }

    #[Test]
    public function deleteConnectedApp(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_ADMIN]]);
        $connectedApp = ConnectedAppFactory::createOne([
            'name' => 'To Delete',
            'code' => AppCode::Fair3r,
            'account' => $user->getAccount(),
            'user' => $user,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(ConnectedApp::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(ConnectedApp::class)->findOneBy(['name' => 'To Delete'])
        );
    }

    #[Test]
    public function getConnectedAppShowsLastSyncTime(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $lastSync = new \DateTime('-1 hour');
        $connectedApp = ConnectedAppFactory::createOne([
            'name' => 'eLabFTW Synced',
            'code' => AppCode::Elabftw,
            'lastSyncAt' => $lastSync,
            'account' => $user->getAccount(),
            'user' => $user,
            'authenticationParameters' => [
                'apiKey' => 'elabftw-api-key',
            ],
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(ConnectedApp::class, ['name' => 'eLabFTW Synced']);

        $response = $client->request('GET', $iri);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('lastSyncAt', $data);
        $this->assertNotNull($data['lastSyncAt']);
        $this->assertSame([
            'apiKey' => '******key',
        ], $data['authenticationParameterHints']);
    }
}
