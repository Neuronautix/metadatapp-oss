<?php

declare(strict_types=1);

namespace App\Tests\Functional\ConnectedApps\Fair3r;

use App\ConnectedApps\Apps\Fair3r\Command\SyncCommandProcessor;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\WeightMeasurementFactory;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SyncCommandProcessorTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function syncCommandProcessor(): void
    {
        $connectedApp = ConnectedAppFactory::new([
            'code' => AppCode::Fair3r,
            'name' => 'Fair3r Test App',
            'isActive' => true,
            'account' => $account = AccountFactory::new([
                'name' => 'Test Account',
            ])->create(),
            'user' => $user = UserFactory::new([
                'email' => 'jasi@po.op',
                'firstName' => 'Jasi',
                'lastName' => 'Pop',
            ])->create(),
        ])->create();
        $experiment = ExperimentFactory::createOne(['user' => $user, 'project' => ProjectFactory::createOne(['externalId' => 'f3r_bw_table'])]);
        $procedure = BehaviorFactory::createOne();
        $experiment->addProcedure($procedure);
        $subjects = SubjectFactory::createMany(4);
        $weightMeasurement = \Zenstruck\Foundry\Persistence\save(WeightMeasurementFactory::createOne(['account' => $account, 'weight' => 1312, 'measuredAt' => new \DateTime(), 'user' => $user, 'subject' => $subjects[0]]));
        $procedure->addSubject($subjects[0]);

        \Zenstruck\Foundry\Persistence\save($experiment);
        /** @var SyncCommandProcessor $processor */
        $processor = self::getContainer()->get(SyncCommandProcessor::class);
        $processor->process();

        \Zenstruck\Foundry\Persistence\refresh($connectedApp);
        $this->assertNotNull($connectedApp->getLastSyncAt());
    }
}
