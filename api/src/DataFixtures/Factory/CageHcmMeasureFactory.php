<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\CageHcmMeasure;
use App\Enum\HcmDataType;

// Ensure this enum class exists and has values
/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\CageHcmMeasure>
 */
final class CageHcmMeasureFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $recordedAt = self::faker()->dateTimeBetween('-1 week', 'now');

        return [
            'cage' => CageFactory::randomOrCreate(),
            'variable' => VariableFactory::randomOrCreate(),
            'dataType' => self::faker()->randomElement(HcmDataType::cases()),
            'value' => self::faker()->randomFloat(2, 0.1, 1000),
            'recordedAt' => $recordedAt,
            'homeCageMonitoring' => HomeCageMonitoringFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return CageHcmMeasure::class;
    }
}
