<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\ZefixFixtures;
use App\Entity\Account;
use App\Entity\Plateau;
use App\Entity\Pool;
use App\Entity\Rack;
use App\Entity\Room;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Entity\ZebrafishLine;
use App\Enum\PoolStatus;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixFixturesTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_loads_a_stable_zefix_demo_dataset(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        (new ZefixFixtures())->load($entityManager);
        $entityManager->clear();

        self::assertSame(4, $entityManager->getRepository(Plateau::class)->count([]));
        self::assertSame(5, $entityManager->getRepository(Room::class)->count([]));
        self::assertSame(9, $entityManager->getRepository(Rack::class)->count([]));
        self::assertSame(23, $entityManager->getRepository(Pool::class)->count([]));
        self::assertSame(5, $entityManager->getRepository(ZebrafishLine::class)->count([]));
        self::assertSame(8, $entityManager->getRepository(ZebrafishBatch::class)->count([]));

        $demoAccount = $entityManager->getRepository(Account::class)->findOneBy(['name' => 'Zefix Demo Facility']);
        self::assertNotNull($demoAccount);

        $demoAdminUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'zefix.demo@metadatapp.net']);
        self::assertNotNull($demoAdminUser);
        self::assertSame($demoAccount->getId(), $demoAdminUser->getAccount()->getId());
        self::assertContains(Roles::ROLE_ADMIN, $demoAdminUser->getRoles());

        $demoStandardUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'zefix.viewer@metadatapp.net']);
        self::assertNotNull($demoStandardUser);
        self::assertSame($demoAccount->getId(), $demoStandardUser->getAccount()->getId());
        self::assertNotContains(Roles::ROLE_ADMIN, $demoStandardUser->getRoles());

        self::assertSame(6, $entityManager->getRepository(Pool::class)->count(['status' => PoolStatus::OCCUPIED]));
        self::assertSame(12, $entityManager->getRepository(Pool::class)->count(['status' => PoolStatus::AVAILABLE]));
        self::assertSame(3, $entityManager->getRepository(Pool::class)->count(['status' => PoolStatus::MAINTENANCE]));
        self::assertSame(2, $entityManager->getRepository(Pool::class)->count(['status' => PoolStatus::INACTIVE]));

        $poolsByRackAndPosition = [];
        foreach ($entityManager->getRepository(Pool::class)->findAll() as $pool) {
            $poolsByRackAndPosition[$pool->getRack()->getCode() . ':' . $pool->getPosition()] = $pool;
            self::assertSame($demoAccount->getId(), $pool->getAccount()->getId());
            self::assertSame($demoAccount->getId(), $pool->getRack()->getAccount()->getId());
            self::assertSame($demoAccount->getId(), $pool->getRack()->getRoom()->getAccount()->getId());
            self::assertSame($demoAccount->getId(), $pool->getRack()->getRoom()->getPlateau()->getAccount()->getId());
        }

        self::assertArrayHasKey('N-01:A1', $poolsByRackAndPosition);
        self::assertArrayHasKey('Q-01:A1', $poolsByRackAndPosition);
        self::assertNotNull($poolsByRackAndPosition['N-01:A1']->getActiveBatch());
        self::assertSame('Tg(elavl3:GCaMP6s)', $poolsByRackAndPosition['N-01:A1']->getActiveBatch()?->getLine()->getName());
        self::assertNull($poolsByRackAndPosition['Q-01:A1']->getActiveBatch());

        foreach ($entityManager->getRepository(ZebrafishBatch::class)->findAll() as $batch) {
            self::assertSame(
                $batch->getMaleCount() + $batch->getFemaleCount() + $batch->getUnknownSexCount(),
                $batch->getTotalPopulation()
            );
            self::assertSame($demoAccount->getId(), $batch->getAccount()->getId());
            self::assertSame($demoAccount->getId(), $batch->getLine()->getAccount()->getId());
            self::assertSame($demoAccount->getId(), $batch->getPool()->getAccount()->getId());
        }
    }
}
