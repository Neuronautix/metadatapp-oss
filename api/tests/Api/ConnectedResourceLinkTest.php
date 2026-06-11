<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ConnectedResourceLinkFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ConnectedResourceLinkTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function unauthenticatedUserCannotListLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connected_resource_links');

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getCollectionScopesByAccount(): void
    {
        $aliceAccount = AccountFactory::createOne();
        $alice = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $aliceAccount,
        ]);
        $bobAccount = AccountFactory::createOne();
        $bob = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $bobAccount,
        ]);

        $aliceExperiment = ExperimentFactory::createOne(['user' => $alice, 'account' => $aliceAccount]);
        $bobExperiment = ExperimentFactory::createOne(['user' => $bob, 'account' => $bobAccount]);

        ConnectedResourceLinkFactory::createMany(2, [
            'experiment' => $aliceExperiment,
            'account' => $aliceAccount,
        ]);
        ConnectedResourceLinkFactory::createOne([
            'experiment' => $bobExperiment,
            'account' => $bobAccount,
        ]);

        $client = static::createClient();
        $client->loginUser($alice);
        $response = $client->request('GET', '/connected_resource_links');

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $response->toArray()['hydra:totalItems']);
    }

    #[Test]
    public function postCreatesLinkAttachedToExperiment(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/connected_resource_links', [
            'json' => [
                'provider' => 'elabftw',
                'externalId' => 'EXP-9999',
                'resourceType' => 'protocol',
                'label' => 'Test protocol link',
                'url' => 'https://demo.elabftw.net/experiments/9999',
                'experiment' => '/experiments/' . $experiment->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $payload = $response->toArray();
        $this->assertSame('elabftw', $payload['provider']);
        $this->assertSame('EXP-9999', $payload['externalId']);
        $this->assertSame('protocol', $payload['resourceType']);
    }

    #[Test]
    public function cannotReadLinkBelongingToAnotherAccount(): void
    {
        $alice = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);
        $bobAccount = AccountFactory::createOne();
        $bob = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $bobAccount,
        ]);
        $bobExperiment = ExperimentFactory::createOne(['user' => $bob, 'account' => $bobAccount]);
        $bobLink = ConnectedResourceLinkFactory::createOne([
            'experiment' => $bobExperiment,
            'account' => $bobAccount,
        ]);

        $client = static::createClient();
        $client->loginUser($alice);
        $client->request('GET', '/connected_resource_links/' . $bobLink->getId());

        $this->assertResponseStatusCodeSame(404);
    }
}
