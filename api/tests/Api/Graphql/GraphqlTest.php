<?php

declare(strict_types=1);

namespace App\Tests\Api\Graphql;

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

class GraphqlTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function queryExperimentsCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        ExperimentFactory::createMany(2, ['account' => $account, 'user' => $user]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/graphql', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'query' => 'query { experiments(first: 2) { edges { node { id name } } } }',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertCount(2, $payload['data']['experiments']['edges']);
    }

    #[Test]
    public function queryBehaviorsCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);
        BehaviorFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
            'name' => 'Behavior GraphQL',
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/graphql', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'query' => 'query { behaviors(first: 1) { edges { node { id name } } } }',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertCount(1, $payload['data']['behaviors']['edges']);
    }

    #[Test]
    public function queryTreatmentsCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);
        TreatmentFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
            'name' => 'Treatment GraphQL',
            'drug' => 'Ibuprofen',
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/graphql', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'query' => 'query { treatments(first: 1) { edges { node { id name } } } }',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertCount(1, $payload['data']['treatments']['edges']);
    }

    #[Test]
    public function querySurgeriesCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);
        SurgeryFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
            'name' => 'Surgery GraphQL',
            'surgeryName' => 'Craniotomy',
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/graphql', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'query' => 'query { surgeries(first: 1) { edges { node { id name } } } }',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertCount(1, $payload['data']['surgeries']['edges']);
    }

    #[Test]
    public function queryExperimentProceduresConnection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);

        BehaviorFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
            'name' => 'Behavior Procedure',
        ]);
        TreatmentFactory::createOne([
            'experiment' => $experiment,
            'account' => $account,
            'user' => $user,
            'name' => 'Treatment Procedure',
            'drug' => 'Metformin',
        ]);

        $iri = $this->findIriBy(Experiment::class, ['id' => $experiment->getId()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/graphql', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'query' => 'query { experiment(id: "' . $iri . '") { procedures(first: 10) { edges { node { id name } } } } }',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertCount(2, $payload['data']['experiment']['procedures']['edges']);
    }
}
