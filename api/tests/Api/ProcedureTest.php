<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\SurgeryFactory;
use App\DataFixtures\Factory\TreatmentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Procedure;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProcedureTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        // Create mixed procedures
        TreatmentFactory::createOne([
            'experiment' => $experiment,
            'user' => $user,
            'account' => $user->getAccount(),
            'name' => 'Treatment 1',
        ]);

        SurgeryFactory::createOne([
            'experiment' => $experiment,
            'user' => $user,
            'account' => $user->getAccount(),
            'name' => 'Surgery 1',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/procedures');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Procedure',
            '@id' => '/procedures',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);

        $members = $response->toArray()['hydra:member'];
        $this->assertCount(2, $members);
        $this->assertArrayHasKey('name', $members[0]);
        $this->assertArrayNotHasKey('study', $members[0]);
        $this->assertArrayNotHasKey('subjects', $members[0]);
        $this->assertArrayNotHasKey('protocol', $members[0]);
    }

    #[Test]
    public function getItem(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);

        $treatment = TreatmentFactory::createOne([
            'experiment' => $experiment,
            'user' => $user,
            'account' => $user->getAccount(),
            'name' => 'Specific Treatment',
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        // Fetch using the generic /assay endpoint
        $client->request('GET', '/assay/' . $treatment->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'name' => 'Specific Treatment',
            'title' => 'Specific Treatment',
        ]);
    }

    #[Test]
    public function filterByExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $experiment1 = ExperimentFactory::createOne(['account' => $account]);
        $experiment2 = ExperimentFactory::createOne(['account' => $account]);

        TreatmentFactory::createOne(['experiment' => $experiment1, 'account' => $account, 'user' => $user]);
        SurgeryFactory::createOne(['experiment' => $experiment1, 'account' => $account, 'user' => $user]);

        TreatmentFactory::createOne(['experiment' => $experiment2, 'account' => $account, 'user' => $user]);

        $client = static::createClient();
        $client->loginUser($user);

        // Check subresource or filter if available.
        $client->request('GET', '/experiments/' . $experiment1->getId() . '/procedures');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'hydra:totalItems' => 2,
        ]);
    }
}
