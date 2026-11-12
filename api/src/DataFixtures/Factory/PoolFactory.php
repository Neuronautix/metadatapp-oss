<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Pool;
use App\Enum\PoolStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Pool>
 */
final class PoolFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_POOLS = [
        ['position' => 'A1', 'capacity' => 80, 'status' => PoolStatus::OCCUPIED],
        ['position' => 'A2', 'capacity' => 80, 'status' => PoolStatus::AVAILABLE],
        ['position' => 'B1', 'capacity' => 60, 'status' => PoolStatus::MAINTENANCE],
        ['position' => 'B2', 'capacity' => 60, 'status' => PoolStatus::AVAILABLE],
        ['position' => 'C1', 'capacity' => 120, 'status' => PoolStatus::OCCUPIED],
        ['position' => 'C2', 'capacity' => 120, 'status' => PoolStatus::INACTIVE],
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $defaults = self::DEFAULT_POOLS[self::$sequence % \count(self::DEFAULT_POOLS)];
        ++self::$sequence;

        return [
            'rack' => RackFactory::new(),
            'position' => $defaults['position'],
            'capacity' => $defaults['capacity'],
            'status' => $defaults['status'],
            'account' => AccountFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Pool $pool): void {
            $pool->setAccount($pool->getRack()->getAccount());
        });
    }

    public static function class(): string
    {
        return Pool::class;
    }
}
