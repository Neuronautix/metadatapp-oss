<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\DemoFixtures;
use App\Entity\Account;
use App\Entity\Cage;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Entity\Treatment;
use App\Entity\User;
use App\Entity\WeightMeasurement;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DemoFixturesTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_loads_a_stable_demo_dataset(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        (new DemoFixtures())->load($entityManager);
        $entityManager->clear();

        $demoAccount = $entityManager->getRepository(Account::class)->findOneBy(['externalKey' => 'metadatapp-demo-lab']);
        self::assertNotNull($demoAccount);

        $demoAdminUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'demo.admin@metadatapp.net']);
        self::assertNotNull($demoAdminUser);
        self::assertSame($demoAccount->getId(), $demoAdminUser->getAccount()->getId());
        self::assertContains(Roles::ROLE_ADMIN, $demoAdminUser->getRoles());

        $demoUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'demo.user@metadatapp.net']);
        self::assertNotNull($demoUser);
        self::assertSame($demoAccount->getId(), $demoUser->getAccount()->getId());

        self::assertSame(2, $entityManager->getRepository(Project::class)->count(['account' => $demoAccount]));
        self::assertSame(3, $entityManager->getRepository(Experiment::class)->count(['account' => $demoAccount]));
        self::assertSame(6, $entityManager->getRepository(Cage::class)->count(['account' => $demoAccount]));
        self::assertSame(18, $entityManager->getRepository(Subject::class)->count(['account' => $demoAccount]));
        self::assertSame(5, $entityManager->getRepository(Treatment::class)->count(['account' => $demoAccount]));
        self::assertSame(18, $entityManager->getRepository(WeightMeasurement::class)->count(['account' => $demoAccount]));

        $subjects = $entityManager->getRepository(Subject::class)->findBy(['account' => $demoAccount]);
        foreach ($subjects as $subject) {
            self::assertGreaterThanOrEqual(1, $subject->getProcedures()->count(), $subject->getName() . ' should be linked to at least one procedure.');
            self::assertGreaterThanOrEqual(1, $subject->getWeightMeasurements()->count(), $subject->getName() . ' should expose at least one sample record.');
        }

        self::assertSame(5, $entityManager->getRepository(ConnectedApp::class)->count(['account' => $demoAccount]));
        self::assertSame(3, $entityManager->getRepository(ConnectedResourceLink::class)->count(['account' => $demoAccount]));
    }
}
