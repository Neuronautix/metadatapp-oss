<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\AnimalFacilityFactory;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\EnvironmentHousingFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\AnimalFacility;
use App\Entity\Cage;
use App\Entity\EnvironmentHousing;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CageTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->loadFixtures();
    }

    #[Test]
    public function getCollection(): void
    {
        $account = AccountFactory::randomOrCreate();

        CageFactory::createMany(100, ['account' => $account]);
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('GET', '/cages');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Cage',
            '@id' => '/cages',
            '@type' => 'hydra:Collection',
        ]);

        $this->assertCount(30, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Cage::class);
    }

    #[Test]
    public function createCage(): void
    {
        $client = static::createClient();
        $account = AccountFactory::randomOrCreate();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('POST', '/cages', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => '/cage_types/' . CageType::Breeding->value,
                'format' => '/cage_formats/' . CageFormat::T1->value,
                'beddingType' => '/bedding_types/' . BeddingType::Osk->value,
                'food' => true,
                'water' => true,
                'animalFacility' => $this->findIriBy(AnimalFacility::class, ['id' => AnimalFacilityFactory::first()->getId()]),
                'environmentHousing' => $this->findIriBy(EnvironmentHousing::class, ['id' => EnvironmentHousingFactory::first()->getId()]),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Cage',
            '@type' => 'Cage',
            'type' => '/cage_types/' . CageType::Breeding->value,
            'format' => '/cage_formats/' . CageFormat::T1->value,
            'beddingType' => '/bedding_types/' . BeddingType::Osk->value,
            'food' => true,
            'water' => true,
        ]);
        $this->assertMatchesRegularExpression('~^/cages/[0-9a-fA-F-]{36}$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Cage::class);
    }

    #[Test]
    public function createInvalidCage(): void
    {
        $client = static::createClient();
        $account = AccountFactory::randomOrCreate();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN], 'account' => $account]));
        $response = $client->request('POST', '/cages', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => '',
                'format' => '/cage_formats/' . CageFormat::T1->value,
                'beddingType' => '/bedding_types/' . BeddingType::Osk->value,
                'food' => true,
                'water' => true,
                'animalFacility' => $this->findIriBy(AnimalFacility::class, ['id' => AnimalFacilityFactory::first()->getId()]),
                'environmentHousing' => $this->findIriBy(EnvironmentHousing::class, ['id' => EnvironmentHousingFactory::first()->getId()]),
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        $this->assertJsonContains([
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Invalid IRI "".',
        ]);
    }

    #[Test]
    public function updateCage(): void
    {
        $account = AccountFactory::randomOrCreate();
        CageFactory::createOne(['type' => CageType::Breeding, 'format' => CageFormat::T1, 'beddingType' => BeddingType::Osk, 'account' => $account]);

        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN]]));
        $iri = $this->findIriBy(Cage::class, ['type' => CageType::Breeding]);

        $client->request('PUT', $iri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => '/cage_types/' . CageType::Breeding->value,
                'format' => '/cage_formats/' . CageFormat::T1->value,
                'beddingType' => '/bedding_types/' . BeddingType::Osk->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'type' => [
                '@id' => '/cage_types/' . CageType::Breeding->value,
                '@type' => 'CageType',
                'value' => CageType::Breeding->value,
            ],
            'format' => [
                '@id' => '/cage_formats/' . CageFormat::T1->value,
                '@type' => 'CageFormat',
                'value' => CageFormat::T1->value,
            ],
            'beddingType' => [
                '@id' => '/bedding_types/' . BeddingType::Osk->value,
                '@type' => 'BeddingType',
                'value' => BeddingType::Osk->value,
            ],
        ]);
    }

    #[Test]
    public function deleteCage(): void
    {
        $account = AccountFactory::randomOrCreate();
        CageFactory::createOne(['type' => CageType::Breeding, 'format' => CageFormat::T1, 'beddingType' => BeddingType::Osk, 'account' => $account]);

        $client = static::createClient();
        $client->loginUser(UserFactory::createOne(['roles' => [Roles::ROLE_SUPER_ADMIN]]));
        $iri = $this->findIriBy(Cage::class, ['type' => CageType::Breeding]);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Cage::class)->findOneBy(['type' => 'classic'])
        );
    }

    private function loadFixtures(): void
    {
        $account = AccountFactory::randomOrCreate();
        AnimalFacilityFactory::createOne(['account' => $account]);
        EnvironmentHousingFactory::createOne(['account' => $account]);
    }
}
