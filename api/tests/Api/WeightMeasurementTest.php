<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\WeightMeasurementFactory;
use App\Entity\WeightMeasurement;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class WeightMeasurementTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        WeightMeasurementFactory::createMany(3, [
            'account' => $user->getAccount(),
            'subject' => $subject,
            'user' => $user, // UserAware
            'measuredAt' => new \DateTime(),
            'weight' => 25.5,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/weight_measurements');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/WeightMeasurement',
            '@id' => '/weight_measurements',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(WeightMeasurement::class);
    }

    #[Test]
    public function createWeightMeasurement(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/weight_measurements', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'subject' => '/subjects/' . $subject->getId(),
                'user' => '/users/' . $user->getId(),
                'weight' => 30.2,
                'measuredAt' => '2024-01-01T10:00:00+00:00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/WeightMeasurement',
            '@type' => 'WeightMeasurement',
            'weight' => 30.2,
        ]);
    }

    #[Test]
    public function updateWeightMeasurement(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $wm = WeightMeasurementFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'user' => $user,
            'weight' => 20.0,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(WeightMeasurement::class, ['weight' => 20.0]);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'weight' => 22.5,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'weight' => 22.5,
        ]);
    }

    #[Test]
    public function deleteWeightMeasurement(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $wm = WeightMeasurementFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'user' => $user,
            'weight' => 999.0,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(WeightMeasurement::class, ['weight' => 999.0]);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(WeightMeasurement::class)->findOneBy(['weight' => 999.0])
        );
    }
}
