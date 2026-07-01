<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\TimeSeries;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\TimeSeries>
 */
final class TimeSeriesFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $recordedAt = self::faker()->dateTimeBetween('-1 month', 'now');

        return [
            'subject' => SubjectFactory::randomOrCreate(),
            'experiment' => ExperimentFactory::randomOrCreate(),
            'variable' => VariableFactory::randomOrCreate(),
            'recordedAt' => $recordedAt,
            'value' => (string) self::faker()->randomFloat(5, 0, 100), // value as string with precision
            'unit' => self::faker()->optional()->randomElement(['m/s', 'mg/kg', '°C']),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return TimeSeries::class;
    }
}
