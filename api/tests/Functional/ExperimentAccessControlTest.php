<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Experiment;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ExperimentAccessControlTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function userCannotUpdateOrDeleteOthersExperiment(): void
    {
        $user1 = UserFactory::createOne();
        $user2 = UserFactory::createOne();
        $experiment = ExperimentFactory::createOne(['user' => $user2, 'name' => 'Experiment 1']);

        $client = static::createClient();
        $client->loginUser($user1);

        $iri = $this->findIriBy(Experiment::class, ['name' => 'Experiment 1']);

        $client->request('PATCH', $iri, [
            'headers' => [
                'content-type' => 'application/ld+json',
            ],
            'json' => [
                'id' => $iri,
                'name' => 'experiment 132',
            ],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $client->request('DELETE', $iri);
        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function unauthenticatedUserCannotAccessExperiments(): void
    {
        $experiment = ExperimentFactory::createOne(['name' => 'Experiment 1']);
        $iri = $this->findIriBy(Experiment::class, ['name' => 'Experiment 1']);
        static::createClient()->request('GET', $iri);
        $this->assertResponseStatusCodeSame(401);

        static::createClient()->request('DELETE', $iri);
        $this->assertResponseStatusCodeSame(401);

        static::createClient()->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Nope'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}
