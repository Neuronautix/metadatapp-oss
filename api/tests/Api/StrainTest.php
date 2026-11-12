<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Strain;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StrainTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        StrainFactory::createMany(3);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/strains');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Strain',
            '@id' => '/strains',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Strain::class);
    }

    #[Test]
    public function createStrain(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/strains', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'C57BL/6J',
                'link' => 'https://www.jax.org/strain/000664',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Strain',
            '@type' => 'Strain',
            'name' => 'C57BL/6J',
            'link' => 'https://www.jax.org/strain/000664',
        ]);
    }

    #[Test]
    public function updateStrain(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $strain = StrainFactory::createOne([
            'name' => 'Original Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Strain::class, ['name' => 'Original Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Name',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function deleteStrain(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $strain = StrainFactory::createOne([
            'name' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Strain::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Strain::class)->findOneBy(['name' => 'To Delete'])
        );
    }
}
