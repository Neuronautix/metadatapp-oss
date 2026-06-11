<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\LaboratoryFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Laboratory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LaboratoryTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        LaboratoryFactory::createMany(3, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/laboratories');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Laboratory',
            '@id' => '/laboratories',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Laboratory::class);
    }

    #[Test]
    public function createLaboratory(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/laboratories', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'New Laboratory',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Laboratory',
            '@type' => 'https://schema.org/Organization',
            'name' => 'New Laboratory',
        ]);
    }

    #[Test]
    public function updateLaboratory(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $lab = LaboratoryFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'Original Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Laboratory::class, ['name' => 'Original Name']);

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
    public function deleteLaboratory(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $lab = LaboratoryFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Laboratory::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Laboratory::class)->findOneBy(['name' => 'To Delete'])
        );
    }
}
