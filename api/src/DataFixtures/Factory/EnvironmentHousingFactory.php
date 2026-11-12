<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\EnvironmentHousing;
use App\Enum\LightCycle;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\EnvironmentHousing>
 */
final class EnvironmentHousingFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'room' => 'Room ' . self::faker()->numberBetween(100, 999),
            'temperature' => self::faker()->optional()->randomFloat(1, 18, 26),
            'humidity' => self::faker()->optional()->randomFloat(1, 30, 70),
            'luminosity' => self::faker()->optional()->randomFloat(1, 100, 10000),
            'pressure' => self::faker()->optional()->randomFloat(2, 950, 1050),
            'noise' => self::faker()->optional()->randomFloat(1, 10, 80),
            'lightCycle' => self::faker()->optional()->randomElement(LightCycle::cases()), // Adjust if LightCycle enum exists and contains values
            'animalFacility' => AnimalFacilityFactory::randomOrCreate(),
            //            'experiment' => self::faker()->boolean(30) ? ExperimentFactory::randomOrCreate() : null,
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return EnvironmentHousing::class;
    }
}
