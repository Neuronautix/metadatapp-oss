<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ImportedReferenceFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Repository\ImportedReferenceRepository;
use App\Security\Roles;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The `main` firewall is stateless, so loginUser() authenticates only one request;
 * each test makes a single authenticated HTTP call, seeding library data through the
 * {@see ImportedReferenceFactory} so it shares the request kernel's database.
 */
final class ImportedReferenceTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    public function testImportPersistsResultsIntoTheAccountLibrary(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $response = $client->request('POST', '/imported_references/import', [
            'json' => ['items' => [
                ['id' => 'jax_phenome:measure:11107', 'source' => 'jax_phenome', 'sourceName' => 'JAX Phenome (MPD)', 'type' => 'measure', 'title' => 'glucose (plasma)', 'externalUrl' => 'https://phenome.jax.org/measureset/11107', 'identifiers' => ['measnum' => 11107], 'raw' => ['measnum' => 11107]],
                ['id' => 'impc:procedure:IMPC_IPG_001', 'source' => 'impc', 'sourceName' => 'IMPReSS (IMPC)', 'type' => 'procedure', 'title' => 'IPGTT'],
            ]],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $payload = $response->toArray();
        $this->assertSame(2, $payload['count']);
        $this->assertNotEmpty($payload['imported'][0]['id']);
        $this->assertStringContainsString('phenome.jax.org', (string) $payload['imported'][0]['externalUrl']);
        $this->assertCount(2, static::getContainer()->get(ImportedReferenceRepository::class)->findBy(['account' => $user->getAccount()]));
    }

    public function testLibraryListReturnsTheAccountReferences(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ImportedReferenceFactory::createOne(['account' => $user->getAccount(), 'title' => 'glucose (plasma)']);

        $client->loginUser($user);
        $library = $client->request('GET', '/imported_references')->toArray();

        $this->assertSame(1, $library['hydra:totalItems']);
        $this->assertSame('glucose (plasma)', $library['hydra:member'][0]['title']);
    }

    public function testLibraryListIsScopedToTheCurrentAccount(): void
    {
        $client = static::createClient();
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ImportedReferenceFactory::createOne(['account' => $owner->getAccount()]);
        // A genuinely separate tenant.
        $other = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => AccountFactory::createOne()]);

        $client->loginUser($other);
        $library = $client->request('GET', '/imported_references')->toArray();

        $this->assertSame(0, $library['hydra:totalItems'], 'The other account must not see the owner library.');
    }

    public function testDeleteRemovesAnImportedReference(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $reference = ImportedReferenceFactory::createOne(['account' => $user->getAccount()]);

        $client->loginUser($user);
        $client->request('DELETE', '/imported_references/' . $reference->getId());

        $this->assertResponseStatusCodeSame(204);
        $this->assertSame(0, ImportedReferenceFactory::repository()->count());
    }

    public function testUnauthenticatedAccessIsDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', '/imported_references');

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }
}
