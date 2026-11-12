<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishBatchFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use App\Zefix\Lines\State\ZefixLineProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ZefixLineProviderTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_filters_line_summaries_by_account_search_and_status_and_builds_detail_batches(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
        ]);

        $otherAccount = AccountFactory::createOne(['name' => 'Other Facility']);
        UserFactory::createOne([
            'account' => $otherAccount,
        ]);

        $plateau = PlateauFactory::createOne([
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'account' => $account,
        ]);
        $room = RoomFactory::createOne([
            'name' => 'Room 101',
            'plateau' => $plateau,
            'account' => $account,
        ]);
        $rack = RackFactory::createOne([
            'code' => 'RACK-01',
            'room' => $room,
            'account' => $account,
        ]);
        $poolA = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A1',
            'capacity' => 80,
            'status' => PoolStatus::OCCUPIED,
            'account' => $account,
        ]);
        $poolB = PoolFactory::createOne([
            'rack' => $rack,
            'position' => 'A2',
            'capacity' => 80,
            'status' => PoolStatus::AVAILABLE,
            'account' => $account,
        ]);

        $activeLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(ins:mCherry)',
            'genotype' => 'Tg(ins:mCherry)s960',
            'purpose' => 'Beta-cell reporter line',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => new \DateTimeImmutable('2024-02-15T08:30:00+00:00'),
            'account' => $account,
        ]);

        $pausedLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(gfap:EGFP)',
            'genotype' => 'Tg(gfap:EGFP)mi2001',
            'purpose' => 'Paused glial reporter',
            'status' => ZebrafishLineStatus::PAUSED,
            'createdAt' => new \DateTimeImmutable('2024-04-02T14:00:00+00:00'),
            'account' => $account,
        ]);

        ZebrafishLineFactory::createOne([
            'name' => 'Tg(mpx:GFP)',
            'genotype' => 'Tg(mpx:GFP)i114',
            'purpose' => 'Should not leak across accounts',
            'status' => ZebrafishLineStatus::ACTIVE,
            'account' => $otherAccount,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $activeLine,
            'pool' => $poolA,
            'birthDate' => new \DateTimeImmutable('2024-03-18'),
            'maleCount' => 10,
            'femaleCount' => 11,
            'unknownSexCount' => 2,
            'totalPopulation' => 23,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE,
            'account' => $account,
        ]);

        ZebrafishBatchFactory::createOne([
            'line' => $activeLine,
            'pool' => $poolB,
            'birthDate' => new \DateTimeImmutable('2024-01-12'),
            'maleCount' => 8,
            'femaleCount' => 7,
            'unknownSexCount' => 1,
            'totalPopulation' => 16,
            'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED,
            'account' => $account,
        ]);

        $entityManager->clear();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $requestStack = new RequestStack();
        $requestStack->push(new Request([
            'search' => 'mCherry',
            'status' => 'active',
        ]));

        $provider = new ZefixLineProvider($entityManager, $security, $requestStack);

        $collection = $provider->provide(new GetCollection());
        self::assertCount(1, $collection);
        self::assertSame('Tg(ins:mCherry)', $collection[0]->name);
        self::assertSame(39, $collection[0]->totalAnimals);
        self::assertSame(2, $collection[0]->activeBatches);
        self::assertSame('Stable', $collection[0]->reproductionStatus);

        $requestStackPaused = new RequestStack();
        $requestStackPaused->push(new Request([
            'status' => 'paused',
        ]));

        $pausedProvider = new ZefixLineProvider($entityManager, $security, $requestStackPaused);
        $pausedCollection = $pausedProvider->provide(new GetCollection());
        self::assertCount(1, $pausedCollection);
        self::assertSame($pausedLine->getId()?->toRfc4122(), $pausedCollection[0]->lineID);
        self::assertSame('Paused', $pausedCollection[0]->reproductionStatus);

        $detail = $provider->provide(
            new Get(),
            ['lineID' => $activeLine->getId()?->toRfc4122() ?? '']
        );

        self::assertSame($activeLine->getId()?->toRfc4122(), $detail->line->lineID);
        self::assertCount(2, $detail->batches);
        self::assertSame(23, $detail->batches[0]->individualCount);
        self::assertSame($poolA->getId()?->toRfc4122(), $detail->batches[0]->locationId);
        self::assertSame($room->getId()?->toRfc4122(), $detail->batches[0]->roomID);
        self::assertStringContainsString('ZF-NORTH', $detail->batches[0]->tankLocation);
    }
}
