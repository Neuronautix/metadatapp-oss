<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\WeightMeasurementFactory;
use App\Security\Roles;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CalendarControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_returns_account_scoped_calendar_events(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $experiment = ExperimentFactory::createOne([
            'account' => $account,
            'user' => $user,
            'name' => 'Study Alpha',
            'startAt' => new \DateTime('2026-04-10T09:00:00+00:00'),
            'endAt' => new \DateTime('2026-04-10T11:00:00+00:00'),
        ]);

        $subject = SubjectFactory::createOne([
            'account' => $account,
            'name' => 'Sample Gamma',
        ]);

        BehaviorFactory::createOne([
            'account' => $account,
            'user' => $user,
            'experiment' => $experiment,
            'name' => 'Protocol Beta',
            'startAt' => new \DateTime('2026-04-11T10:00:00+00:00'),
            'endAt' => new \DateTime('2026-04-11T11:30:00+00:00'),
            'state' => 'planned',
            'subjects' => new ArrayCollection([$subject]),
        ]);

        $measurement = WeightMeasurementFactory::createOne([
            'account' => $account,
            'user' => $user,
            'subject' => $subject,
            'measuredAt' => new \DateTime('2026-04-12T08:15:00+00:00'),
        ]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);
        ExperimentFactory::createOne([
            'account' => $otherUser->getAccount(),
            'user' => $otherUser,
            'name' => 'Other Account Study',
            'startAt' => new \DateTime('2026-04-09T09:00:00+00:00'),
            'endAt' => new \DateTime('2026-04-09T11:00:00+00:00'),
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('GET', '/calendar/events?start=2026-04-01T00%3A00%3A00%2B00%3A00&end=2026-04-30T23%3A59%3A59%2B00%3A00');

        $this->assertResponseIsSuccessful();

        $payload = $response->toArray();
        $this->assertCount(3, $payload['data']);
        $this->assertSame(['study', 'protocol', 'sample'], array_column($payload['data'], 'kind'));
        $this->assertSame(['Study Alpha', 'Protocol Beta', 'Weight sample - Sample Gamma'], array_column($payload['data'], 'title'));
        $this->assertSame((string) $subject->getId(), $payload['data'][1]['subjects'][0]['id']);
        $this->assertSame('Sample Gamma', $payload['data'][1]['subjects'][0]['label']);
        $this->assertSame((string) $measurement->getId(), $payload['data'][2]['resource']['id']);
        $this->assertSame('Weight sample - Sample Gamma', $payload['data'][2]['resource']['label']);
        $this->assertSame((string) $subject->getId(), $payload['data'][2]['subject']['id']);
        $this->assertSame('Sample Gamma', $payload['data'][2]['subject']['label']);
    }
}
