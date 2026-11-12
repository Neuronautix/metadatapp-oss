<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\TreatmentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\WeightMeasurementFactory;
use App\Enum\HealthStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

final class ProjectWorkspaceControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function projectDashboardReturnsLinkedAnimalSummary(): void
    {
        [$user, $project] = $this->createProjectWithLinkedSubject();

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/projects/%s/dashboard', $project->getId()));

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        self::assertSame((string) $project->getId(), $payload['investigationId']);
        self::assertCount(1, $payload['animalsAtRisk']);
        self::assertSame('Linked animals', $payload['markerTrends'][0]['label']);
        self::assertNotEmpty($payload['groupWeights']);
        self::assertNotEmpty($payload['recentActions']);
    }

    #[Test]
    public function projectAnimalsEndpointReturnsUiAnimalPayloads(): void
    {
        [$user, $project] = $this->createProjectWithLinkedSubject();

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/experiments/%s/animals', $project->getId()));

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        self::assertCount(1, $payload['data']);
        self::assertSame((string) $project->getId(), $payload['data'][0]['investigationId']);
        self::assertArrayHasKey('lastWeight', $payload['data'][0]);
    }

    #[Test]
    public function animalDetailEndpointsReturnUiPayloads(): void
    {
        [$user, $project, $subject] = $this->createProjectWithLinkedSubject();

        $client = static::createClient();
        $client->loginUser($user);

        $detailResponse = $client->request('GET', \sprintf('/animals/%s', $subject->getId()));
        $this->assertResponseIsSuccessful();
        $detail = $detailResponse->toArray();
        self::assertSame('Mouse-001', $detail['nickname']);
        self::assertSame((string) $project->getId(), $detail['investigationId']);

        $weightsClient = static::createClient();
        $weightsClient->loginUser($user);
        $weightsResponse = $weightsClient->request('GET', \sprintf('/animals/%s/weights', $subject->getId()));
        $this->assertResponseIsSuccessful();
        self::assertEquals(24.0, $weightsResponse->toArray()['data'][0]['grams']);

        $timelineClient = static::createClient();
        $timelineClient->loginUser($user);
        $timelineResponse = $timelineClient->request('GET', \sprintf('/animals/%s/timeline', $subject->getId()));
        $this->assertResponseIsSuccessful();
        $timeline = $timelineResponse->toArray();
        self::assertNotEmpty($timeline['data']);
        self::assertContains('weight', array_column($timeline['data'], 'kind'));
        self::assertContains('treatment', array_column($timeline['data'], 'kind'));
    }

    #[Test]
    public function projectAnimalsExportReturnsCsv(): void
    {
        [$user, $project] = $this->createProjectWithLinkedSubject();

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', \sprintf('/projects/%s/animals/export', $project->getId()));

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        self::assertSame(1, $payload['rowCount']);
        self::assertStringStartsWith('id,tagId,nickname,cage,lastWeight', $payload['csv']);
    }

    #[Test]
    public function projectWorkspaceActivityEndpointReturnsRecentProjects(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ProjectFactory::createOne([
            'name' => 'Workspace Activity Project',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/project-workspace/activity');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        self::assertSame('Workspace Activity Project', $payload['data'][0]['investigationName']);
    }

    #[Test]
    public function keyboardShortcutsEndpointReturnsUiContract(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/keyboard-shortcuts');

        $this->assertResponseIsSuccessful();
        self::assertArrayHasKey('keys', $response->toArray()['data'][0]);
    }

    #[Test]
    public function projectDashboardForbidsOtherAccount(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/projects/%s/dashboard', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{0: \App\Entity\User, 1: \App\Entity\Project, 2: \App\Entity\Subject}
     */
    private function createProjectWithLinkedSubject(): array
    {
        $account = AccountFactory::createOne();
        $user = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => $account,
        ]);
        $project = ProjectFactory::createOne([
            'name' => 'DVC Linked Project',
            'user' => $user,
            'account' => $account,
        ]);
        $experiment = ExperimentFactory::createOne([
            'project' => $project,
            'user' => $user,
            'account' => $account,
        ]);
        $procedure = TreatmentFactory::createOne([
            'experiment' => $experiment,
            'user' => $user,
            'account' => $account,
        ]);
        $subject = SubjectFactory::createOne([
            'name' => 'Mouse-001',
            'account' => $account,
            'healthStatus' => HealthStatus::WARNING_LEVEL_1,
        ]);
        WeightMeasurementFactory::createOne([
            'subject' => $subject,
            'user' => $user,
            'account' => $account,
            'weight' => 0.024,
            'measuredAt' => new \DateTime('2026-04-01T10:00:00+00:00'),
        ]);

        $procedure->addSubject($subject);
        save($procedure);

        return [$user, $project, $subject];
    }
}
