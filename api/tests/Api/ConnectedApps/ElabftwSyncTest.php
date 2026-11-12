<?php

declare(strict_types=1);

namespace App\Tests\Api\ConnectedApps;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ConnectedApps\Apps\Elabftw\Client\Client;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentCreateRequestDto;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests for ElabFTW synchronization functionality.
 * Tests the complete sync flow using the ElabFTW mock HTTP client.
 */
class ElabftwSyncTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->client = static::getContainer()->get(Client::class);
    }

    #[Test]
    public function elabftwClientCanFetchExperiments(): void
    {
        $experiments = $this->client->experiments()->list();

        $this->assertNotEmpty($experiments);
        $this->assertIsArray($experiments);
        $this->assertGreaterThanOrEqual(4, \count($experiments), 'Should have at least 4 experiments from mock');

        $first = $experiments[0];
        $this->assertSame(1, $first->id);
        $this->assertNotEmpty($first->title);
        $this->assertIsArray($first->tags);
    }

    #[Test]
    public function elabftwClientCanFetchSingleExperiment(): void
    {
        $experiment = $this->client->experiments()->get(1);

        $this->assertSame(1, $experiment->id);
        $this->assertSame('Open Field Test - Cohort A', $experiment->title);
        $this->assertContains('open-field', $experiment->tags);
    }

    #[Test]
    public function elabftwClientCanCreateExperiment(): void
    {
        $dto = new ExperimentCreateRequestDto(
            title: 'New Sync Test Experiment',
            body: '<p>Body from MAPP sync test.</p>',
            tags: ['mapp-sync', 'test'],
            status: 1,
        );

        $created = $this->client->experiments()->create($dto);

        $this->assertGreaterThan(0, $created->id, 'Created experiment should have a valid ID');
    }

    #[Test]
    public function elabftwClientCanFetchItems(): void
    {
        $items = $this->client->items()->list();

        $this->assertNotEmpty($items);
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(4, \count($items), 'Should have at least 4 items from mock');

        $first = $items[0];
        $this->assertSame(1, $first->id);
        $this->assertNotEmpty($first->title);
    }

    #[Test]
    public function elabftwClientCanFetchUsers(): void
    {
        $users = $this->client->users()->list();

        $this->assertNotEmpty($users);
        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(1, \count($users));

        $first = $users[0];
        $this->assertSame(1, $first->userid);
        $this->assertSame('Damien', $first->firstname);
    }

    #[Test]
    public function elabftwSyncCommandCompletesSuccessfully(): void
    {
        $account = AccountFactory::new()->create();
        $user = UserFactory::new(['account' => $account])->create();
        ConnectedAppFactory::new([
            'code' => AppCode::Elabftw,
            'name' => 'Elabftw Integration Test',
            'isActive' => true,
            'account' => $account,
            'user' => $user,
        ])->create();

        $application = new Application(static::$kernel);
        $command = $application->find('app:sync:elabftw');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Synchronization completed successfully.', $commandTester->getDisplay());
    }
}
