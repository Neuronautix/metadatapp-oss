<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\TimeSeriesFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\Entity\TimeSeries;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TimeSeriesTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        TimeSeriesFactory::createMany(3, [
            'account' => $user->getAccount(),
            'subject' => $subject,
            'experiment' => $experiment,
            'variable' => $variable,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/time_series');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/TimeSeries',
            '@id' => '/time_series',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(TimeSeries::class);
    }

    #[Test]
    public function createTimeSeries(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/time_series', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'subject' => '/subjects/' . $subject->getId(),
                'experiment' => '/experiments/' . $experiment->getId(),
                'variable' => '/variables/' . $variable->getId(),
                'value' => '12.34',
                'recordedAt' => '2024-01-01T10:00:00+00:00',
                'unit' => 'kg',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/TimeSeries',
            '@type' => 'TimeSeries',
            'value' => '12.34000',
            'unit' => 'kg',
        ]);
    }

    #[Test]
    public function updateTimeSeries(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $ts = TimeSeriesFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'experiment' => $experiment,
            'variable' => $variable,
            'value' => '10.00',
            'unit' => 'cm', // Need to make sure factory supports this, or update to override default
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(TimeSeries::class, ['value' => '10.00']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'value' => '20.00',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'value' => '20.00000',
        ]);
    }

    #[Test]
    public function deleteTimeSeries(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne(['account' => $user->getAccount()]);
        $experiment = ExperimentFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $ts = TimeSeriesFactory::createOne([
            'account' => $user->getAccount(),
            'subject' => $subject,
            'experiment' => $experiment,
            'variable' => $variable,
            'value' => '999.00',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(TimeSeries::class, ['value' => '999.00']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(TimeSeries::class)->findOneBy(['value' => '999.00'])
        );
    }
}
