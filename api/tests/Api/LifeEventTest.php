<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\LifeEventFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\LifeEvent;
use App\Enum\HealthStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LifeEventTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);
        LifeEventFactory::createMany(3, [
            'account' => $user->getAccount(),
            'subject' => $subject,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/life_events');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/LifeEvent',
            '@id' => '/life_events',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(LifeEvent::class);
    }

    #[Test]
    public function createLifeEvent(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/life_events', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'subject' => '/subjects/' . $subject->getId(),
                'eventType' => '/health_statuses/' . HealthStatus::NORMAL->value,
                'eventAt' => '2024-01-01T10:00:00+00:00',
                'description' => 'Recovered',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/LifeEvent',
            '@type' => 'LifeEvent',
            'eventType' => '/health_statuses/' . HealthStatus::NORMAL->value,
            'description' => 'Recovered',
        ]);
    }

    #[Test]
    public function updateLifeEvent(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $lifeEvent = LifeEventFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'description' => 'Sick',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(LifeEvent::class, ['description' => 'Sick']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'description' => 'Better',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'description' => 'Better',
        ]);
    }

    #[Test]
    public function deleteLifeEvent(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);

        $lifeEvent = LifeEventFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'description' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(LifeEvent::class, ['description' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(LifeEvent::class)->findOneBy(['description' => 'To Delete'])
        );
    }
}
