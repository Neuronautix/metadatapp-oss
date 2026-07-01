<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AnimalFacilityFactory;
use App\DataFixtures\Factory\EnvironmentHousingFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\EnvironmentHousing;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class EnvironmentHousingTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['account' => $user->getAccount()]);
        EnvironmentHousingFactory::createMany(3, [
            'account' => $user->getAccount(),
            'animalFacility' => $facility,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/environment_housings');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/EnvironmentHousing',
            '@id' => '/environment_housings',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(EnvironmentHousing::class);
    }

    #[Test]
    public function createEnvironmentHousing(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/environment_housings', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'room' => 'Room 101',
                'animalFacility' => '/animal_facilities/' . $facility->getId(),
                'temperature' => 22.5,
                'humidity' => 50.0,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/EnvironmentHousing',
            '@type' => 'EnvironmentHousing',
            'room' => 'Room 101',
            'temperature' => 22.5,
        ]);
    }

    #[Test]
    public function updateEnvironmentHousing(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['account' => $user->getAccount()]);

        $env = EnvironmentHousingFactory::createOne([
            'account' => $user->getAccount(),
            'animalFacility' => $facility,
            'room' => 'Original Room',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(EnvironmentHousing::class, ['room' => 'Original Room']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'room' => 'Updated Room',
                'temperature' => 25.0,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'room' => 'Updated Room',
            'temperature' => 25,
        ]);
    }

    #[Test]
    public function deleteEnvironmentHousing(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['account' => $user->getAccount()]);

        $env = EnvironmentHousingFactory::createOne([
            'account' => $user->getAccount(),
            'animalFacility' => $facility,
            'room' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(EnvironmentHousing::class, ['room' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(EnvironmentHousing::class)->findOneBy(['room' => 'To Delete'])
        );
    }
}
