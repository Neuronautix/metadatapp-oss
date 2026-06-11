<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\WeightMeasurement;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\WeightMeasurement>
 */
final class WeightMeasurementFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'subject' => SubjectFactory::randomOrCreate(),
            'measuredAt' => self::faker()->dateTimeBetween('-3 months', 'now'),
            // Assuming weight is in kg. If your subjects are small animals like mice, adjust accordingly.
            'weight' => self::faker()->randomFloat(3, 0.01, 0.05), // e.g., mice weights ~10-50g = 0.01-0.05 kg
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return WeightMeasurement::class;
    }
}
