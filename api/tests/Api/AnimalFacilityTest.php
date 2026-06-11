<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\AnimalFacilityFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\AnimalFacility;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AnimalFacilityTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facilities = AnimalFacilityFactory::createMany(100, ['account' => $user->getAccount()]);
        $client = static::createClient();

        $client->loginUser($user);

        $response = $client->request('GET', '/animal_facilities');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/AnimalFacility',
            '@id' => '/animal_facilities',
            '@type' => 'hydra:Collection',
        ]);

        $this->assertCount(30, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(AnimalFacility::class);
    }

    #[Test]
    public function createAnimalFacility(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();

        $client->loginUser($user);
        $response = $client->request('POST', '/animal_facilities', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Test Animal Facility',
                'sanitaryStatus' => 'A1',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/AnimalFacility',
            '@type' => 'AnimalFacility',
            'name' => 'Test Animal Facility',
            'sanitaryStatus' => 'A1',
        ]);
        $this->assertMatchesRegularExpression('~^/animal_facilities/[0-9a-fA-F-]{36}$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(AnimalFacility::class);
    }

    #[Test]
    public function createInvalidAnimalFacility(): void
    {
        $account = AccountFactory::randomOrCreate();

        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('POST', '/animal_facilities', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => '',
                'sanitaryStatus' => '',
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
    public function updateAnimalFacility(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['name' => 'Original Name', 'sanitaryStatus' => 'A1', 'account' => $user->getAccount()]);
        $iri = $this->findIriBy(AnimalFacility::class, ['name' => 'Original Name']);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Updated Name',
                'sanitaryStatus' => 'A2',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Name',
            'sanitaryStatus' => 'A2',
        ]);
    }

    #[Test]
    public function deleteAnimalFacility(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $facility = AnimalFacilityFactory::createOne(['name' => 'Animal Facility to Delete', 'sanitaryStatus' => 'A1', 'account' => $user->getAccount()]);
        $iri = $this->findIriBy(AnimalFacility::class, ['name' => 'Animal Facility to Delete']);

        $client = static::createClient();
        $client->loginUser($user);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(AnimalFacility::class)->findOneBy(['name' => 'Animal Facility to Delete'])
        );
    }
}
