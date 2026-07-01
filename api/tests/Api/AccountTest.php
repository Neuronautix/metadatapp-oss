<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Account;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AccountTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        AccountFactory::createMany(100);

        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN]]));
        $response = $client->request('GET', '/accounts');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Account',
            '@id' => '/accounts',
            '@type' => 'hydra:Collection',
        ]);

        $this->assertCount(30, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Account::class);
    }

    #[Test]
    public function getCollectionForbidsRegularUsers(): void
    {
        AccountFactory::createMany(2);

        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_USER]]));
        $client->request('GET', '/accounts');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function createAccount(): void
    {
        $client = static::createClient();

        $account = AccountFactory::randomOrCreate();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('POST', '/accounts', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Test Account',
                'externalKey' => 'test-account',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Account',
            '@type' => 'Account',
            'name' => 'Test Account',
            'externalKey' => 'test-account',
        ]);
        $this->assertMatchesRegularExpression('~^/accounts/[0-9a-fA-F-]{36}$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Account::class);
    }

    #[Test]
    public function createInvalidAccount(): void
    {
        $client = static::createClient();
        $account = AccountFactory::randomOrCreate();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('POST', '/accounts', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $this->assertJsonContains([
            '@context' => '/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'name: This value should not be blank.',
        ]);
    }

    #[Test]
    public function updateAccount(): void
    {
        AccountFactory::createOne(['name' => 'Original Name']);
        $client = static::createClient();
        $account = AccountFactory::randomOrCreate();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));

        $iri = $this->findIriBy(Account::class, ['name' => 'Original Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Updated Name',
                'externalKey' => 'updated-account',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Name',
            'externalKey' => 'updated-account',
        ]);
    }
}
