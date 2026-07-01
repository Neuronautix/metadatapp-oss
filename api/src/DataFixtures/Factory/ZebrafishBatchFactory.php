<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ZebrafishBatch;
use App\Enum\ZebrafishBatchLifecycleStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ZebrafishBatch>
 */
final class ZebrafishBatchFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_BATCHES = [
        ['birthDate' => '2024-02-01', 'maleCount' => 12, 'femaleCount' => 14, 'unknownSexCount' => 4, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['birthDate' => '2024-03-18', 'maleCount' => 10, 'femaleCount' => 11, 'unknownSexCount' => 2, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['birthDate' => '2024-05-07', 'maleCount' => 9, 'femaleCount' => 9, 'unknownSexCount' => 0, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED],
        ['birthDate' => '2023-12-12', 'maleCount' => 15, 'femaleCount' => 16, 'unknownSexCount' => 1, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::COMPLETED],
        ['birthDate' => '2023-10-01', 'maleCount' => 8, 'femaleCount' => 7, 'unknownSexCount' => 0, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ARCHIVED],
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $defaults = self::DEFAULT_BATCHES[self::$sequence % \count(self::DEFAULT_BATCHES)];
        ++self::$sequence;

        $totalPopulation = $defaults['maleCount'] + $defaults['femaleCount'] + $defaults['unknownSexCount'];

        return [
            'line' => ZebrafishLineFactory::new(),
            'pool' => PoolFactory::new(),
            'birthDate' => new \DateTimeImmutable($defaults['birthDate']),
            'maleCount' => $defaults['maleCount'],
            'femaleCount' => $defaults['femaleCount'],
            'unknownSexCount' => $defaults['unknownSexCount'],
            'totalPopulation' => $totalPopulation,
            'lifecycleStatus' => $defaults['lifecycleStatus'],
            'account' => AccountFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (ZebrafishBatch $batch): void {
            $account = $batch->getPool()->getAccount();
            $batch->setAccount($account);
            $batch->getLine()->setAccount($account);
            $batch->setTotalPopulation($batch->getMaleCount() + $batch->getFemaleCount() + $batch->getUnknownSexCount());
        });
    }

    public static function class(): string
    {
        return ZebrafishBatch::class;
    }
}
