<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Behavior;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BehaviorTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;
    //
    //    #[Test]
    //    public function getCollection(): void
    //    {
    //        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
    //        BehaviorFactory::createMany(3, ['account' => $user->getAccount()]);
    //
    //        $client = static::createClient();
    //        $client->loginUser($user->_real());
    //        $client->request('GET', '/behaviors');
    //
    //        $this->assertResponseIsSuccessful();
    //        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    //
    //        $this->assertJsonContains([
    //            '@context' => '/contexts/Behavior',
    //            '@id' => '/behaviors',
    //            '@type' => 'hydra:Collection',
    //            'hydra:totalItems' => 3,
    //        ]);
    //
    //        $this->assertMatchesResourceCollectionJsonSchema(Behavior::class);
    //    }

    #[Test]
    public function getCollectionForExperiment(): void
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
            'name' => 'Experiment Behavior',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/experiments/' . $experiment->getId() . '/procedures');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@id' => '/experiments/' . $experiment->getId() . '/procedures',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);
        $this->assertCount(1, $response->toArray()['hydra:member']);
    }

    #[Test]
    public function createBehavior(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $client->request('POST', '/behaviors', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'experiment' => '/experiments/' . $experiment->getId(),
                'name' => 'New Behavior Test',
                'description' => 'Test description',
                'apparatus' => 'Open Field Test',
                'lighting' => 120.5,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Behavior',
            '@type' => 'https://schema.org/MedicalProcedure', // Behavior uses this type
            'name' => 'New Behavior Test',
            'description' => 'Test description',
            'apparatus' => 'Open Field Test',
            'lighting' => 120.5,
        ]);
    }

    #[Test]
    public function updateBehavior(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $behavior = BehaviorFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'Original Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Behavior::class, ['name' => 'Original Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Name',
                'lighting' => 50.0,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Name',
            'lighting' => 50,
        ]);
    }

    #[Test]
    public function deleteBehavior(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        BehaviorFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'To Delete',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Behavior::class, ['name' => 'To Delete']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(405);
    }
}
