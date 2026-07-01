<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\CageHcmSinusoidMetadata;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\CageHcmSinusoidMetadata>
 */
final class CageHcmSinusoidMetadataFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'cage' => CageFactory::randomOrCreate(),
            'homeCageMonitoring' => HomeCageMonitoringFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
            'date' => self::faker()->dateTimeBetween('-30 days', 'now'),
            'amplitude' => self::faker()->randomFloat(2, 1.0, 50.0),
            'mesor' => self::faker()->randomFloat(2, 5.0, 100.0),
            'phase' => self::faker()->randomFloat(4, -3.14159, 3.14159),
            'peakHour' => self::faker()->randomFloat(2, 0.0, 23.9),
        ];
    }

    public static function class(): string
    {
        return CageHcmSinusoidMetadata::class;
    }
}
