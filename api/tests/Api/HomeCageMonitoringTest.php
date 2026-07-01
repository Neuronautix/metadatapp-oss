<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\HomeCageMonitoringFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\HomeCageMonitoring;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HomeCageMonitoringTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);
        HomeCageMonitoringFactory::createMany(3, [
            'account' => $user->getAccount(),
            'experiment' => $experiment,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/home_cage_monitorings');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/HomeCageMonitoring',
            '@id' => '/home_cage_monitorings',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(HomeCageMonitoring::class);
    }

    #[Test]
    public function createHomeCageMonitoring(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/home_cage_monitorings', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'system' => 'DVC Digital Cage',
                'experiment' => '/experiments/' . $experiment->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/HomeCageMonitoring',
            '@type' => 'HomeCageMonitoring',
            'system' => 'DVC Digital Cage',
        ]);
    }

    #[Test]
    public function updateHomeCageMonitoring(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        $hcm = HomeCageMonitoringFactory::createOne([
            'account' => $user->getAccount(),
            'experiment' => $experiment,
            'system' => 'Original System',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(HomeCageMonitoring::class, ['system' => 'Original System']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'system' => 'Updated System',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'system' => 'Updated System',
        ]);
    }

    #[Test]
    public function deleteHomeCageMonitoring(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        $hcm = HomeCageMonitoringFactory::createOne([
            'account' => $user->getAccount(),
            'experiment' => $experiment,
            'system' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(HomeCageMonitoring::class, ['system' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(HomeCageMonitoring::class)->findOneBy(['system' => 'To Delete'])
        );
    }
}
