<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\AppCode;
use App\Security\Roles;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ReferenceSearchTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFederatedSearchReturnsNormalizedResultsFromTheUsersActiveApps(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ConnectedAppFactory::createOne([
            'code' => AppCode::JaxPhenome,
            'token' => null,
            'account' => $user->getAccount(),
            'user' => $user,
            'isActive' => true,
            'externalUrl' => 'https://phenome.jax.org',
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/reference-search?q=body+weight&apps=jax_phenome');

        $this->assertResponseIsSuccessful();
        $members = $response->toArray()['hydra:member'];
        $this->assertNotEmpty($members);

        $measures = array_values(array_filter(
            $members,
            static fn (array $m): bool => 'jax_phenome' === $m['source'] && 'measure' === $m['type'],
        ));
        $this->assertNotEmpty($measures, 'Expected at least one JAX Phenome measure result.');
        $this->assertArrayHasKey('externalUrl', $measures[0]);
        $this->assertStringContainsString('phenome.jax.org/measureset/', (string) $measures[0]['externalUrl']);
    }

    public function testPublicReferenceAppsAreSearchableWithoutActivation(): void
    {
        // A brand-new user with NO connected apps still gets results from the
        // always-on public reference databases (no activation required).
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/reference-search?q=body+weight&apps=jax_phenome');

        $this->assertResponseIsSuccessful();
        $members = $response->toArray()['hydra:member'];
        $this->assertNotEmpty($members, 'Public JAX Phenome must be searchable without activation.');
        $this->assertSame('jax_phenome', $members[0]['source']);
    }

    public function testUnauthenticatedIsDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reference-search?q=blood');

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }
}
