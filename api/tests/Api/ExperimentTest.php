<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\SurgeryFactory;
use App\DataFixtures\Factory\TreatmentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Experiment;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ExperimentTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ExperimentFactory::createMany(3, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/experiments');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Experiment',
            '@id' => '/experiments',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
    }

    #[Test]
    public function getProceduresCollectionForExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne([
            'account' => $account,
            'user' => $user,
        ]);

        BehaviorFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
        ]);
        TreatmentFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
        ]);
        SurgeryFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/experiments/' . $experiment->getId() . '/procedures');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@id' => '/experiments/' . $experiment->getId() . '/procedures',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);
        $this->assertCount(3, $response->toArray()['hydra:member']);
    }

    #[Test]
    public function createExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/experiments', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'New Experiment',
                'startAt' => '2024-01-01T12:00:00+00:00',
                'description' => 'Test description',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Experiment',
            '@type' => 'Experiment',
            'name' => 'New Experiment',
            'title' => 'New Experiment',
            'description' => 'Test description',
        ]);
    }

    #[Test]
    public function updateExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'Original Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Experiment::class, ['name' => 'Original Name']);

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
    public function deleteExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Experiment::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Experiment::class)->findOneBy(['name' => 'To Delete'])
        );
    }
}
