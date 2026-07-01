<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\Entity\Variable;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class VariableTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        VariableFactory::createMany(3, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/variables');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Variable',
            '@id' => '/variables',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Variable::class);
    }

    #[Test]
    public function createVariable(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/variables', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Temperature',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Variable',
            '@type' => 'Variable',
            'name' => 'Temperature',
        ]);
    }

    #[Test]
    public function updateVariable(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $variable = VariableFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'Old Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Variable::class, ['name' => 'Old Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'New Name',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function deleteVariable(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $variable = VariableFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Variable::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Variable::class)->findOneBy(['name' => 'To Delete'])
        );
    }
}
