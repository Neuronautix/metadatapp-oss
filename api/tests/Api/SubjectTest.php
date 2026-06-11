<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Cage;
use App\Entity\Strain;
use App\Entity\Subject;
use App\Enum\AppCode;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SubjectTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subjects = SubjectFactory::createMany(100, ['account' => $user->getAccount()]);
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/subjects');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Subject',
            '@id' => '/subjects',
            '@type' => 'hydra:Collection',
        ]);

        $this->assertCount(30, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Subject::class);
    }

    #[Test]
    public function createSubject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        StrainFactory::createMany(5);
        $subjects = SubjectFactory::createMany(100, ['account' => $user->getAccount()]);
        $cages = CageFactory::createMany(5, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/subjects', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'MS-2624',
                'species' => '/species/' . Species::Rat->value,
                'sex' => '/sexes/' . Sex::Male->value,
                'birthAt' => '2025-08-08 11:00',
                'strain' => $this->findIriBy(Strain::class, ['id' => StrainFactory::first()->getId()]),
                'genotype' => 'GEN',
                'cage' => $this->findIriBy(Cage::class, ['id' => CageFactory::first()->getId()]),
                'weight' => 2,
                'healthStatus' => '/health_statuses/' . rawurlencode(HealthStatus::WARNING_LEVEL_1->value),
                'isPregnant' => false,
            ]]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Subject',
            '@type' => 'Subject',
            'name' => 'MS-2624',
        ]);
        $this->assertMatchesRegularExpression('~^/subjects/[0-9a-fA-F-]{36}$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Subject::class);
    }

    #[Test]
    public function createInvalidSubject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        StrainFactory::createMany(5);
        $subjects = SubjectFactory::createMany(100, ['account' => $user->getAccount()]);
        $cages = CageFactory::createMany(5, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/subjects', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['weight' => -12],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $this->assertJsonContains([
            '@context' => '/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'weight: This value should be greater than or equal to 0.',
        ]);
    }

    #[Test]
    public function getItemWithConnectedAppProvider(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        ConnectedAppFactory::createOne([
            'code' => AppCode::Elabftw,
            'name' => 'eLabFTW App',
            'isActive' => true,
            'account' => $account,
            'user' => $user,
        ]);
        $subject = SubjectFactory::createOne(['account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Subject::class, ['id' => $subject->getId()]);

        $client->request('GET', $iri);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
        ]);
    }

    #[Test]
    public function updateSubject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['name' => 'MS-5248', 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Subject::class, ['name' => 'MS-5248']);

        $client->request('PUT', $iri, [
            'headers' => [
                'content-type' => 'application/ld+json',
            ],
            'json' => [
                '@context' => '/contexts/Subject',
                '@id' => $iri,
                '@type' => 'Subject',
                'id' => (string) $subject->getId(),
                'weight' => 2,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'MS-5248',
            'weight' => 2,
        ]);
    }

    #[Test]
    public function deleteSubject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['name' => 'MS-1778', 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Subject::class, ['name' => 'MS-1778']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Subject::class)->findOneBy(['name' => 'MS-1778'])
        );
    }
}
