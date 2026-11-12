<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\HomeCageMonitoring;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\HomeCageMonitoring>
 */
final class HomeCageMonitoringFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'experiment' => ExperimentFactory::randomOrCreate(),
            'system' => self::faker()->randomElement(['HCMsystemX', 'HCMsystemY', 'HCMsystemZ']),
            'account' => AccountFactory::randomOrCreate(),
            'lightsOnUtcHour' => null,
            'lightDurationHours' => 12,
        ];
    }

    public static function class(): string
    {
        return HomeCageMonitoring::class;
    }
}
